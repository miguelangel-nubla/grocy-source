<?php

namespace Grocy\Controllers;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Grocy\Helpers\Grocycode;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class InventoryContainerController extends BaseController
{
	use GrocycodeTrait;

	private const WEIGHT_PRECISION_TOLERANCE = 0.001;
	private const CONTAINER_WEIGHT_USERFIELD = 'StockEntryContainerWeight';

	public function __construct(Container $container)
	{
		parent::__construct($container);
	}

	private function getContainerWeightUserfield()
	{
		return $this->getDatabase()->userfields()
			->where('entity = :1 AND name = :2', 'stock', self::CONTAINER_WEIGHT_USERFIELD)
			->fetch();
	}

	private function getStockEntry($productId, $stockId)
	{
		return $this->getDatabase()->stock()
			->where('product_id = :1 AND stock_id = :2', $productId, $stockId)
			->fetch();
	}

	private function getContainerWeight($stockId, $userfieldId)
	{
		$containerWeightValue = $this->getDatabase()->userfield_values()
			->where('object_id = :1 AND field_id = :2', $stockId, $userfieldId)
			->fetch();

		return $containerWeightValue ? floatval($containerWeightValue->value) : 0;
	}

	private function validateGrossWeight($grossWeight, $containerWeight)
	{
		if ($containerWeight === 0) {
			throw new \Exception('Container weight not set for this stock entry. Please set the Stock Entry Container Weight user field.');
		}

		if ($grossWeight < $containerWeight) {
			throw new \Exception("Gross weight cannot be less than container weight ({$containerWeight})");
		}
	}

	private function hasSignificantChange($amountDifference)
	{
		return abs($amountDifference) >= self::WEIGHT_PRECISION_TOLERANCE;
	}

	private function createStockLogEntry($data)
	{
		return $this->getStockService()->createStockLogEntry($data);
	}

	private function handleStockIncrease($requestBody, $productId, $stockId, $stockEntry, $amountDifference, $netWeight, $transactionId)
	{
		$sourceStockId = $requestBody['source_stock_entry'] ?? null;
		$allowRemainingModification = !empty($requestBody['partial_transfer_mode']) && $requestBody['partial_transfer_mode'] === 'add_remaining';

		// Handle case with no source selected
		if (empty($sourceStockId)) {
			if (!$allowRemainingModification) {
				throw new \Exception('Stock increased. Please specify where this stock came from or select how to handle the stock increase.');
			}

			// Add stock without source
			return $this->handleStockAdditionWithoutSource($productId, $stockId, $stockEntry, $amountDifference, $netWeight, $transactionId);
		}

		// Handle case with source selected
		$sourceStockEntry = $this->getStockEntry($productId, $sourceStockId);
		if (!$sourceStockEntry) {
			throw new \Exception('Source stock entry not found');
		}

		$availableFromSource = floatval($sourceStockEntry->amount);

		if ($availableFromSource >= $amountDifference) {
			// Source has enough stock for full transfer
			return $this->handleFullTransfer($productId, $sourceStockId, $stockId, $sourceStockEntry, $stockEntry, $amountDifference, $netWeight, $transactionId);
		}

		// Partial transfer scenario
		if (!$allowRemainingModification) {
			throw new \Exception('Source stock entry does not have enough stock for this transfer. Select "Add remaining stock without source" to allow partial transfer.');
		}

		return $this->handleTransferWithSourceShortfall($productId, $sourceStockId, $stockId, $sourceStockEntry, $stockEntry, $amountDifference, $netWeight, $availableFromSource, $transactionId);
	}

	private function handleStockAdditionWithoutSource($productId, $stockId, $stockEntry, $amountDifference, $netWeight, $transactionId)
	{
		$this->getDatabase()->beginTransaction();

		$stockEntry->update(['amount' => $netWeight]);

		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => $amountDifference,
			'best_before_date' => $stockEntry->best_before_date,
			'purchased_date' => $stockEntry->purchased_date,
			'stock_id' => $stockId,
			'transaction_type' => 'inventory-correction',
			'price' => $stockEntry->price,
			'location_id' => $stockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory: Stock added without source transfer"
		]);

		$this->getDatabase()->commitTransaction();
	}

	private function handleFullTransfer($productId, $sourceStockId, $stockId, $sourceStockEntry, $stockEntry, $amountDifference, $netWeight, $transactionId)
	{
		$this->getDatabase()->beginTransaction();

		// Update amounts using StockService for proper cleanup
		$newSourceAmount = floatval($sourceStockEntry->amount) - $amountDifference;
		$this->getStockService()->UpdateStockEntryAmount($sourceStockEntry, $newSourceAmount);
		$stockEntry->update(['amount' => $netWeight]);

		// Log transfers
		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => -$amountDifference,
			'best_before_date' => $sourceStockEntry->best_before_date,
			'purchased_date' => $sourceStockEntry->purchased_date,
			'stock_id' => $sourceStockId,
			'transaction_type' => 'transfer_from',
			'price' => $sourceStockEntry->price,
			'location_id' => $sourceStockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory transfer to stock_id {$stockId}"
		]);

		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => $amountDifference,
			'best_before_date' => $stockEntry->best_before_date,
			'purchased_date' => $stockEntry->purchased_date,
			'stock_id' => $stockId,
			'transaction_type' => 'transfer_to',
			'price' => $stockEntry->price,
			'location_id' => $stockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory transfer from stock_id {$sourceStockId}"
		]);

		$this->getDatabase()->commitTransaction();
	}

	private function handleTransferWithSourceShortfall($productId, $sourceStockId, $stockId, $sourceStockEntry, $destinationStockEntry, $amountDifference, $netWeight, $availableFromSource, $transactionId)
	{
		$remainingAmount = $this->calculatePreciseDifference($amountDifference, $availableFromSource);
		$currentDestinationAmount = BigDecimal::of($this->normalizeNumericInput($destinationStockEntry->amount));
		$availableDecimal = BigDecimal::of($this->normalizeNumericInput($availableFromSource));
		$remainingDecimal = BigDecimal::of($this->normalizeNumericInput($remainingAmount));
		$destinationAmountAfterTransfer = floatval($currentDestinationAmount->plus($availableDecimal)->__toString());

		$this->getDatabase()->beginTransaction();

		// Step 1: transfer every available unit from the source entry
		$this->getStockService()->UpdateStockEntryAmount($sourceStockEntry, 0);
		$destinationStockEntry->update(['amount' => $destinationAmountAfterTransfer]);

		// Log transfer from source
		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => -$availableFromSource,
			'best_before_date' => $sourceStockEntry->best_before_date,
			'purchased_date' => $sourceStockEntry->purchased_date,
			'stock_id' => $sourceStockId,
			'transaction_type' => 'transfer_from',
			'price' => $sourceStockEntry->price,
			'location_id' => $sourceStockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory partial transfer to stock_id {$stockId}"
		]);

		// Log transferred amount to destination
		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => $availableFromSource,
			'best_before_date' => $destinationStockEntry->best_before_date,
			'purchased_date' => $destinationStockEntry->purchased_date,
			'stock_id' => $stockId,
			'transaction_type' => 'transfer_to',
			'price' => $destinationStockEntry->price,
			'location_id' => $destinationStockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory partial transfer from stock_id {$sourceStockId}"
		]);

		// Step 2: add the missing amount as a direct inventory adjustment
		$destinationStockEntry->update(['amount' => $netWeight]);
		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => floatval($remainingDecimal->__toString()),
			'best_before_date' => $destinationStockEntry->best_before_date,
			'purchased_date' => $destinationStockEntry->purchased_date,
			'stock_id' => $stockId,
			'transaction_type' => 'inventory-correction',
			'price' => $destinationStockEntry->price,
			'location_id' => $destinationStockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory: Additional stock added directly (source does not have enough available)"
		]);

		$this->getDatabase()->commitTransaction();
	}

	// Use BigDecimal to subtract without relying on float arithmetic
	private function calculatePreciseDifference($minuend, $subtrahend, int $scale = 6): string
	{
		$result = BigDecimal::of($this->normalizeNumericInput($minuend))
			->minus(BigDecimal::of($this->normalizeNumericInput($subtrahend)))
			->toScale($scale, RoundingMode::HALF_UP)
			->__toString();

		return rtrim(rtrim($result, '0'), '.') ?: '0';
	}

	private function normalizeNumericInput($value): string
	{
		if ($value instanceof BigDecimal) {
			return $value->__toString();
		}

		if (is_string($value)) {
			$value = trim($value);
			return $value === '' ? '0' : $value;
		}

		if (is_int($value)) {
			return (string) $value;
		}

		if (is_float($value)) {
			$stringValue = sprintf('%.10F', $value);
			return rtrim(rtrim($stringValue, '0'), '.') ?: '0';
		}

		return '0';
	}

	private function handleStockDecrease($requestBody, $productId, $stockId, $stockEntry, $amountDifference, $netWeight, $transactionId)
	{
		$consumeAmount = abs($amountDifference);
		$destinationType = $requestBody['destination_type'] ?? 'consume';

		if ($destinationType === 'transfer') {
			$this->handleTransferDecrease($requestBody, $productId, $stockId, $stockEntry, $consumeAmount, $netWeight, $transactionId);
		} else {
			$this->handleConsumption($productId, $stockId, $stockEntry, $consumeAmount, $netWeight, $transactionId, $requestBody['gross_weight'] ?? 0);
		}
	}

	private function handleTransferDecrease($requestBody, $productId, $stockId, $sourceStockEntry, $consumeAmount, $netWeight, $transactionId)
	{
		if (empty($requestBody['destination_stock_entry'])) {
			throw new \Exception('Stock decreased. Please specify destination stock entry for transfer.');
		}

		$destStockId = $requestBody['destination_stock_entry'];
		$destStockEntry = $this->getStockEntry($productId, $destStockId);

		if (!$destStockEntry) {
			throw new \Exception('Destination stock entry not found');
		}

		$this->getDatabase()->beginTransaction();

		// Update amounts
		$sourceStockEntry->update(['amount' => $netWeight]);
		$destStockEntry->update(['amount' => floatval($destStockEntry->amount) + $consumeAmount]);

		// Log transfers
		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => -$consumeAmount,
			'best_before_date' => $sourceStockEntry->best_before_date,
			'purchased_date' => $sourceStockEntry->purchased_date,
			'stock_id' => $stockId,
			'transaction_type' => 'transfer_from',
			'price' => $sourceStockEntry->price,
			'location_id' => $sourceStockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory transfer to stock_id {$destStockId}"
		]);

		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => $consumeAmount,
			'best_before_date' => $destStockEntry->best_before_date,
			'purchased_date' => $destStockEntry->purchased_date,
			'stock_id' => $destStockId,
			'transaction_type' => 'transfer_to',
			'price' => $destStockEntry->price,
			'location_id' => $destStockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => "Container inventory transfer from stock_id {$stockId}"
		]);

		$this->getDatabase()->commitTransaction();
	}

	private function handleConsumption($productId, $stockId, $stockEntry, $consumeAmount, $netWeight, $transactionId, $grossWeight)
	{
		$this->getDatabase()->beginTransaction();

		$stockEntry->update(['amount' => $netWeight]);

		$this->createStockLogEntry([
			'product_id' => $productId,
			'amount' => -$consumeAmount,
			'best_before_date' => $stockEntry->best_before_date,
			'purchased_date' => $stockEntry->purchased_date,
			'stock_id' => $stockId,
			'transaction_type' => 'consume',
			'price' => $stockEntry->price,
			'location_id' => $stockEntry->location_id,
			'transaction_id' => $transactionId,
			'user_id' => GROCY_USER_ID,
			'note' => sprintf('Container inventory consumption: Gross %.2f, Container %.2f, Net %.2f',
				$grossWeight, $this->getContainerWeight($stockId, null), $netWeight)
		]);

		$this->getDatabase()->commitTransaction();
	}

	public function InventoryContainer(Request $request, Response $response, array $args)
	{
		$containerWeightUserfield = $this->getContainerWeightUserfield();

		if (!$containerWeightUserfield) {
			return $this->renderPage($response, 'inventorycontainer_setup', [
				'required_userfield_name' => self::CONTAINER_WEIGHT_USERFIELD,
				'required_userfield_entity' => 'stock'
			]);
		}

		return $this->renderPage($response, 'inventorycontainer', [
			'container_weight_userfield' => $containerWeightUserfield,
			'quantityUnits' => $this->getDatabase()->quantity_units()->orderBy('name', 'COLLATE NOCASE'),
			'locations' => $this->getDatabase()->locations()->orderBy('name', 'COLLATE NOCASE'),
			'weight_precision_tolerance' => self::WEIGHT_PRECISION_TOLERANCE
		]);
	}

	public function InventoryContainerPost(Request $request, Response $response, array $args)
	{
		$requestBody = $request->getParsedBody();

		try
		{
			// Validate required parameters
			if (empty($requestBody['container_tag']))
			{
				throw new \Exception('Container tag barcode is required');
			}

			if (!isset($requestBody['gross_weight']) || $requestBody['gross_weight'] === '')
			{
				throw new \Exception('Gross weight is required');
			}

			// Parse the Grocycode to get product_id and stock_id
			if (!Grocycode::Validate($requestBody['container_tag']))
			{
				throw new \Exception('Invalid barcode format');
			}

			$grocycode = new Grocycode($requestBody['container_tag']);
			if ($grocycode->GetType() != Grocycode::PRODUCT)
			{
				throw new \Exception('Barcode is not a product code');
			}

			$productId = $grocycode->GetId();
			$extraData = $grocycode->GetExtraData();

			if (empty($extraData) || empty($extraData[0]))
			{
				throw new \Exception('Barcode does not contain stock entry information');
			}

			$stockId = $extraData[0];

			// Find the stock entry
			$stockEntry = $this->getStockEntry($productId, $stockId);
			if (!$stockEntry) {
				throw new \Exception('Stock entry not found');
			}

			// Get the container weight from userfield
			$containerWeightUserfield = $this->getContainerWeightUserfield();
			if (!$containerWeightUserfield) {
				throw new \Exception('Stock Entry Container Weight userfield not found');
			}

			$containerWeight = $this->getContainerWeight($stockId, $containerWeightUserfield->id);
			$grossWeight = floatval($requestBody['gross_weight']);

			// Validate gross weight
			$this->validateGrossWeight($grossWeight, $containerWeight);

			$netWeight = $grossWeight - $containerWeight;
			$oldAmount = floatval($stockEntry->amount);
			$amountDifference = $netWeight - $oldAmount;

			// Check if there's no significant change
			if (!$this->hasSignificantChange($amountDifference)) {
				throw new \Exception('No inventory change detected - adjust the gross weight to reflect actual inventory');
			}

			$transactionId = UniqueId();

			if ($amountDifference > 0) {
				$this->handleStockIncrease($requestBody, $productId, $stockId, $stockEntry, $amountDifference, $netWeight, $transactionId);
			} else {
				$this->handleStockDecrease($requestBody, $productId, $stockId, $stockEntry, $amountDifference, $netWeight, $transactionId);
			}

			return $response->withJson([
				'success' => true,
				'stock_entry' => $stockEntry,
				'gross_weight' => $grossWeight,
				'container_weight' => $containerWeight,
				'net_weight' => $netWeight,
				'old_amount' => $oldAmount,
				'amount_difference' => $amountDifference,
				'transaction_id' => $transactionId
			]);
		}
		catch (\Exception $ex)
		{
			return $response->withJson([
				'success' => false,
				'error' => $ex->getMessage()
			], 400);
		}
	}
}
