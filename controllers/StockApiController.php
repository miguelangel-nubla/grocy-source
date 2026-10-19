<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Services\StockService;
use Grocy\Helpers\WebhookRunner;
use Grocy\Helpers\Grocycode;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StockApiController extends BaseApiController
{
	public function AddMissingProductsToShoppingList(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_SHOPPINGLIST_ITEMS_ADD);

		try {
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$listId = 1;

			if (array_key_exists('list_id', $requestBody) && !empty($requestBody['list_id']) && is_numeric($requestBody['list_id'])) {
				$listId = intval($requestBody['list_id']);
			}

			$this->getStockService()->AddMissingProductsToShoppingList($listId);
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function AddOverdueProductsToShoppingList(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_SHOPPINGLIST_ITEMS_ADD);

		try {
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$listId = 1;

			if (array_key_exists('list_id', $requestBody) && !empty($requestBody['list_id']) && is_numeric($requestBody['list_id'])) {
				$listId = intval($requestBody['list_id']);
			}

			$this->getStockService()->AddOverdueProductsToShoppingList($listId);
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function AddExpiredProductsToShoppingList(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_SHOPPINGLIST_ITEMS_ADD);

		try {
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$listId = 1;

			if (array_key_exists('list_id', $requestBody) && !empty($requestBody['list_id']) && is_numeric($requestBody['list_id'])) {
				$listId = intval($requestBody['list_id']);
			}

			$this->getStockService()->AddExpiredProductsToShoppingList($listId);
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function AddProduct(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_PURCHASE);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('amount', $requestBody)) {
				throw new \Exception('An amount is required');
			}

			$bestBeforeDate = null;
			if (array_key_exists('best_before_date', $requestBody) && IsIsoDate($requestBody['best_before_date'])) {
				$bestBeforeDate = $requestBody['best_before_date'];
			}

			$purchasedDate = date('Y-m-d');
			if (array_key_exists('purchased_date', $requestBody) && IsIsoDate($requestBody['purchased_date'])) {
				$purchasedDate = $requestBody['purchased_date'];
			}

			$price = null;
			if (array_key_exists('price', $requestBody) && is_numeric($requestBody['price'])) {
				$price = $requestBody['price'];
			}

			$locationId = null;
			if (array_key_exists('location_id', $requestBody) && is_numeric($requestBody['location_id'])) {
				$locationId = $requestBody['location_id'];
			}

			$shoppingLocationId = null;
			if (array_key_exists('shopping_location_id', $requestBody) && is_numeric($requestBody['shopping_location_id'])) {
				$shoppingLocationId = $requestBody['shopping_location_id'];
			}

			$transactionType = StockService::TRANSACTION_TYPE_PURCHASE;
			if (array_key_exists('transaction_type', $requestBody) && !empty($requestBody['transaction_type'])) {
				$transactionType = $requestBody['transaction_type'];
			}

			$stockLabelType = 0;
			if (array_key_exists('stock_label_type', $requestBody) && is_numeric($requestBody['stock_label_type'])) {
				$stockLabelType = intval($requestBody['stock_label_type']);
			}

			$note = null;
			if (array_key_exists('note', $requestBody)) {
				$note = $requestBody['note'];
			}

			$transactionId = $this->getStockService()->AddProduct($args['productId'], $requestBody['amount'], $bestBeforeDate, $transactionType, $purchasedDate, $price, $locationId, $shoppingLocationId, $unusedTransactionId, $stockLabelType, false, $note);

			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function AddProductByBarcode(Request $request, Response $response, array $args)
	{
		try {
			$args['productId'] = $this->getStockService()->GetProductIdFromBarcode($args['barcode']);

			// If no amount provided, use the configured barcode amount
			$requestBody = $request->getParsedBody();
			if ($requestBody === null || !array_key_exists('amount', $requestBody) || empty($requestBody['amount'])) {
				$defaultAmount = $this->getStockService()->GetBarcodeAmount($args['barcode']);
				if ($requestBody === null) {
					$requestBody = [];
				}
				$requestBody['amount'] = $defaultAmount;
				$request = $request->withParsedBody($requestBody);
			}

			return $this->AddProduct($request, $response, $args);
		} catch (\Exception $ex) {
			$this->logFailedBarcodeScan($request, $args['barcode'], 'add', $ex->getMessage());
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function AddProductToShoppingList(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_SHOPPINGLIST_ITEMS_ADD);

		try {
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$listId = 1;
			$amount = 1;
			$quId = -1;
			$productId = null;
			$note = null;

			if (array_key_exists('list_id', $requestBody) && !empty($requestBody['list_id']) && is_numeric($requestBody['list_id'])) {
				$listId = intval($requestBody['list_id']);
			}

			if (array_key_exists('product_amount', $requestBody) && !empty($requestBody['product_amount']) && is_numeric($requestBody['product_amount'])) {
				$amount = floatval($requestBody['product_amount']);
			}

			if (array_key_exists('product_id', $requestBody) && !empty($requestBody['product_id']) && is_numeric($requestBody['product_id'])) {
				$productId = intval($requestBody['product_id']);
			}

			if (array_key_exists('note', $requestBody) && !empty($requestBody['note'])) {
				$note = $requestBody['note'];
			}

			if (array_key_exists('qu_id', $requestBody) && !empty($requestBody['qu_id'])) {
				$quId = $requestBody['qu_id'];
			}

			if ($productId == null) {
				throw new \Exception('No product id was supplied');
			}

			$this->getStockService()->AddProductToShoppingList($productId, $amount, $quId, $note, $listId);
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ClearShoppingList(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_SHOPPINGLIST_ITEMS_DELETE);

		try {
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$listId = 1;
			if (array_key_exists('list_id', $requestBody) && !empty($requestBody['list_id']) && is_numeric($requestBody['list_id'])) {
				$listId = intval($requestBody['list_id']);
			}

			$doneOnly = false;
			if (array_key_exists('done_only', $requestBody) && filter_var($requestBody['done_only'], FILTER_VALIDATE_BOOLEAN) !== false) {
				$doneOnly = boolval($requestBody['done_only']);
			}

			$this->getStockService()->ClearShoppingList($listId, $doneOnly);
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ConsumeProduct(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_CONSUME);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('amount', $requestBody)) {
				throw new \Exception('An amount is required');
			}

			$spoiled = false;
			if (array_key_exists('spoiled', $requestBody)) {
				$spoiled = $requestBody['spoiled'];
			}

			$transactionType = StockService::TRANSACTION_TYPE_CONSUME;
			if (array_key_exists('transaction_type', $requestBody) && !empty($requestBody['transactiontype'])) {
				$transactionType = $requestBody['transactiontype'];
			}

			$specificStockEntryId = 'default';
			if (array_key_exists('stock_entry_id', $requestBody) && !empty($requestBody['stock_entry_id'])) {
				$specificStockEntryId = $requestBody['stock_entry_id'];
			}

			$locationId = null;
			if (array_key_exists('location_id', $requestBody) && !empty($requestBody['location_id']) && is_numeric($requestBody['location_id'])) {
				$locationId = $requestBody['location_id'];
			}

			$recipeId = null;
			if (array_key_exists('recipe_id', $requestBody) && is_numeric($requestBody['recipe_id'])) {
				$recipeId = $requestBody['recipe_id'];
			}

			$consumeExact = false;
			if (array_key_exists('exact_amount', $requestBody)) {
				$consumeExact = $requestBody['exact_amount'];
			}

			$allowSubproductSubstitution = false;
			if (array_key_exists('allow_subproduct_substitution', $requestBody)) {
				$allowSubproductSubstitution = $requestBody['allow_subproduct_substitution'];
			}

			$transactionId = null;
			$transactionId = $this->getStockService()->ConsumeProduct($args['productId'], $requestBody['amount'], $spoiled, $transactionType, $specificStockEntryId, $recipeId, $locationId, $transactionId, $allowSubproductSubstitution, $consumeExact);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ConsumeProductByBarcode(Request $request, Response $response, array $args)
	{
		try {
			// Check if this is a Grocycode with stock entry ID - redirect to stock entry endpoint
			if (Grocycode::Validate($args['barcode'])) {
				$gc = new Grocycode($args['barcode']);
				if ($gc->GetExtraData() && !empty($gc->GetExtraData()[0])) {
					$args['entryId'] = $gc->GetExtraData()[0];
					return $this->ConsumeStockEntry($request, $response, $args);
				}
			}

			$args['productId'] = $this->getStockService()->GetProductIdFromBarcode($args['barcode']);

			// If no amount provided, use the configured barcode amount
			$requestBody = $request->getParsedBody();
			if ($requestBody === null || !array_key_exists('amount', $requestBody) || empty($requestBody['amount'])) {
				$defaultAmount = $this->getStockService()->GetBarcodeAmount($args['barcode']);
				if ($requestBody === null) {
					$requestBody = [];
				}
				$requestBody['amount'] = $defaultAmount;

				// If the amount was not supplied (so the default amount is used),
				// check if the current stock amount is less than the default amount
				// If so, use the current stock amount to prevent an error (consume what is there)
				$productDetails = $this->getStockService()->GetProductDetails($args['productId']);
				if ($defaultAmount > $productDetails['stock_amount_aggregated'] && $productDetails['stock_amount_aggregated'] > 0) {
					$requestBody['amount'] = $productDetails['stock_amount_aggregated'];
				}

				$request = $request->withParsedBody($requestBody);
			}

			return $this->ConsumeProduct($request, $response, $args);
		} catch (\Exception $ex) {
			$this->logFailedBarcodeScan($request, $args['barcode'], 'consume', $ex->getMessage());
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ConsumeStockEntry(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_CONSUME);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			// Get the stock entry to find the product ID and current amount
			$stockEntry = $this->getStockService()->GetStockEntry($args['entryId']);
			if ($stockEntry === null) {
				throw new \Exception('Stock entry not found');
			}

			// Determine amount to consume
			$amount = $stockEntry->amount; // Default to full amount
			if ($requestBody !== null && array_key_exists('amount', $requestBody) && is_numeric($requestBody['amount'])) {
				$amount = $requestBody['amount'];

				// Validate that requested amount doesn't exceed available amount
				if ($amount > $stockEntry->amount) {
					throw new \Exception('Cannot consume more than available stock (' . $stockEntry->amount . ')');
				}
			}

			$spoiled = false;
			if ($requestBody !== null && array_key_exists('spoiled', $requestBody)) {
				$spoiled = $requestBody['spoiled'];
			}

			$transactionType = StockService::TRANSACTION_TYPE_CONSUME;
			if ($requestBody !== null && array_key_exists('transaction_type', $requestBody) && !empty($requestBody['transaction_type'])) {
				$transactionType = $requestBody['transaction_type'];
			}

			$recipeId = null;
			if ($requestBody !== null && array_key_exists('recipe_id', $requestBody) && is_numeric($requestBody['recipe_id'])) {
				$recipeId = $requestBody['recipe_id'];
			}

			$locationId = null;
			if ($requestBody !== null && array_key_exists('location_id', $requestBody) && !empty($requestBody['location_id']) && is_numeric($requestBody['location_id'])) {
				$locationId = $requestBody['location_id'];
			}

			// Use the specific stock entry ID
			$specificStockEntryId = $args['entryId'];
			$consumeExact = true; // Always consume exact amount for specific stock entries
			$allowSubproductSubstitution = false; // Don't allow substitution when targeting specific entry

			$transactionId = null;
			$transactionId = $this->getStockService()->ConsumeProduct($stockEntry->product_id, $amount, $spoiled, $transactionType, $specificStockEntryId, $recipeId, $locationId, $transactionId, $allowSubproductSubstitution, $consumeExact);

			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function TransferStockEntry(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_TRANSFER);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			// Get the stock entry to find the product ID and current amount
			$stockEntry = $this->getStockService()->GetStockEntry($args['entryId']);
			if ($stockEntry === null) {
				throw new \Exception('Stock entry not found');
			}

			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('location_id_to', $requestBody)) {
				throw new \Exception('A transfer to location is required');
			}

			// Determine amount to transfer
			$amount = $stockEntry->amount; // Default to full amount
			if (array_key_exists('amount', $requestBody) && is_numeric($requestBody['amount'])) {
				$amount = $requestBody['amount'];

				// Validate that requested amount doesn't exceed available amount
				if ($amount > $stockEntry->amount) {
					throw new \Exception('Cannot transfer more than available stock (' . $stockEntry->amount . ')');
				}
			}

			// The "from" location is the current location of the stock entry
			$locationIdFrom = $stockEntry->location_id;
			$locationIdTo = $requestBody['location_id_to'];

			// Use the specific stock entry ID
			$specificStockEntryId = $args['entryId'];

			$transactionId = $this->getStockService()->TransferProduct($stockEntry->product_id, $amount, $locationIdFrom, $locationIdTo, $specificStockEntryId);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function InventoryStockEntry(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_INVENTORY);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			// Get the stock entry to find the product ID and current details
			$stockEntry = $this->getStockService()->GetStockEntry($args['entryId']);
			if ($stockEntry === null) {
				throw new \Exception('Stock entry not found');
			}

			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('new_amount', $requestBody)) {
				throw new \Exception('A new amount is required');
			}

			$newAmount = $requestBody['new_amount'];

			// Use existing values as defaults, allow overrides from request
			$bestBeforeDate = $stockEntry->best_before_date;
			if (array_key_exists('best_before_date', $requestBody) && IsIsoDate($requestBody['best_before_date'])) {
				$bestBeforeDate = $requestBody['best_before_date'];
			}

			$purchasedDate = $stockEntry->purchased_date;
			if (array_key_exists('purchased_date', $requestBody) && IsIsoDate($requestBody['purchased_date'])) {
				$purchasedDate = $requestBody['purchased_date'];
			}

			$locationId = $stockEntry->location_id;
			if (array_key_exists('location_id', $requestBody) && is_numeric($requestBody['location_id'])) {
				$locationId = $requestBody['location_id'];
			}

			$price = $stockEntry->price;
			if (array_key_exists('price', $requestBody) && is_numeric($requestBody['price'])) {
				$price = $requestBody['price'];
			}

			$shoppingLocationId = $stockEntry->shopping_location_id;
			if (array_key_exists('shopping_location_id', $requestBody) && is_numeric($requestBody['shopping_location_id'])) {
				$shoppingLocationId = $requestBody['shopping_location_id'];
			}

			$stockLabelType = 0;
			if (array_key_exists('stock_label_type', $requestBody) && is_numeric($requestBody['stock_label_type'])) {
				$stockLabelType = intval($requestBody['stock_label_type']);
			}

			$note = null;
			if (array_key_exists('note', $requestBody)) {
				$note = $requestBody['note'];
			}

			// Use the new InventoryStockEntry method to update only this specific stock entry
			$transactionId = $this->getStockService()->InventoryStockEntry($args['entryId'], $newAmount, $bestBeforeDate, $locationId, $price, $shoppingLocationId, $purchasedDate, $stockLabelType, $note);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function OpenStockEntry(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_OPEN);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			// Get the stock entry to find the product ID
			$stockEntry = $this->getStockService()->GetStockEntry($args['entryId']);
			if ($stockEntry === null) {
				throw new \Exception('Stock entry not found');
			}

			// Determine amount to open - default to full amount of the stock entry
			$amount = $stockEntry->amount;
			if ($requestBody !== null && array_key_exists('amount', $requestBody) && is_numeric($requestBody['amount'])) {
				$amount = $requestBody['amount'];

				// Validate that requested amount doesn't exceed available amount
				if ($amount > $stockEntry->amount) {
					throw new \Exception('Cannot open more than available stock (' . $stockEntry->amount . ')');
				}
			}

			$allowSubproductSubstitution = false;
			if ($requestBody !== null && array_key_exists('allow_subproduct_substitution', $requestBody)) {
				$allowSubproductSubstitution = $requestBody['allow_subproduct_substitution'];
			}

			$transactionId = null;
			$transactionId = $this->getStockService()->OpenProduct($stockEntry->product_id, $amount, $args['entryId'], $transactionId, $allowSubproductSubstitution);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CurrentStock(Request $request, Response $response, array $args)
	{
		return $this->ApiResponse($response, $this->getStockService()->GetCurrentStock());
	}

	public function CurrentVolatileStock(Request $request, Response $response, array $args)
	{
		$nextXDays = 5;

		if (isset($request->getQueryParams()['due_soon_days']) && !empty($request->getQueryParams()['due_soon_days']) && is_numeric($request->getQueryParams()['due_soon_days'])) {
			$nextXDays = $request->getQueryParams()['due_soon_days'];
		}

		$dueProducts = $this->getStockService()->GetDueProducts($nextXDays, true);
		$overdueProducts = $this->getStockService()->GetDueProducts(-1);
		$expiredProducts = $this->getStockService()->GetExpiredProducts();
		$missingProducts = $this->getStockService()->GetMissingProducts();
		return $this->ApiResponse($response, [
			'due_products' => $dueProducts,
			'overdue_products' => $overdueProducts,
			'expired_products' => $expiredProducts,
			'missing_products' => $missingProducts
		]);
	}

	public function EditStockEntry(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_EDIT);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('amount', $requestBody)) {
				throw new \Exception('An amount is required');
			}

			$bestBeforeDate = null;
			if (array_key_exists('best_before_date', $requestBody) && IsIsoDate($requestBody['best_before_date'])) {
				$bestBeforeDate = $requestBody['best_before_date'];
			}

			$price = null;
			if (array_key_exists('price', $requestBody) && is_numeric($requestBody['price'])) {
				$price = $requestBody['price'];
			}

			$locationId = null;
			if (array_key_exists('location_id', $requestBody) && is_numeric($requestBody['location_id'])) {
				$locationId = $requestBody['location_id'];
			}

			$shoppingLocationId = null;
			if (array_key_exists('shopping_location_id', $requestBody) && is_numeric($requestBody['shopping_location_id'])) {
				$shoppingLocationId = $requestBody['shopping_location_id'];
			}

			$note = null;
			if (array_key_exists('note', $requestBody)) {
				$note = $requestBody['note'];
			}

			$transactionId = $this->getStockService()->EditStockEntry($args['entryId'], $requestBody['amount'], $bestBeforeDate, $locationId, $shoppingLocationId, $price, $requestBody['open'], $requestBody['purchased_date'], $note);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ExternalBarcodeLookup(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_MASTER_DATA_EDIT);

		try {
			$addFoundProduct = false;
			if (isset($request->getQueryParams()['add']) && ($request->getQueryParams()['add'] === 'true' || $request->getQueryParams()['add'] === 1)) {
				$addFoundProduct = true;
			}

			return $this->ApiResponse($response, $this->getStockService()->ExternalBarcodeLookup($args['barcode'], $addFoundProduct));
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function InventoryProduct(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_INVENTORY);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('new_amount', $requestBody)) {
				throw new \Exception('An new amount is required');
			}

			$bestBeforeDate = null;
			if (array_key_exists('best_before_date', $requestBody) && IsIsoDate($requestBody['best_before_date'])) {
				$bestBeforeDate = $requestBody['best_before_date'];
			}

			$purchasedDate = null;
			if (array_key_exists('purchased_date', $requestBody) && IsIsoDate($requestBody['purchased_date'])) {
				$purchasedDate = $requestBody['purchased_date'];
			}

			$locationId = null;
			if (array_key_exists('location_id', $requestBody) && is_numeric($requestBody['location_id'])) {
				$locationId = $requestBody['location_id'];
			}

			$price = null;
			if (array_key_exists('price', $requestBody) && is_numeric($requestBody['price'])) {
				$price = $requestBody['price'];
			}

			$shoppingLocationId = null;
			if (array_key_exists('shopping_location_id', $requestBody) && is_numeric($requestBody['shopping_location_id'])) {
				$shoppingLocationId = $requestBody['shopping_location_id'];
			}

			$stockLabelType = 0;
			if (array_key_exists('stock_label_type', $requestBody) && is_numeric($requestBody['stock_label_type'])) {
				$stockLabelType = intval($requestBody['stock_label_type']);
			}

			$note = null;
			if (array_key_exists('note', $requestBody)) {
				$note = $requestBody['note'];
			}

			$transactionId = $this->getStockService()->InventoryProduct($args['productId'], $requestBody['new_amount'], $bestBeforeDate, $locationId, $price, $shoppingLocationId, $purchasedDate, $stockLabelType, $note);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function InventoryProductByBarcode(Request $request, Response $response, array $args)
	{
		try {
			// Check if this is a Grocycode with stock entry ID - redirect to stock entry endpoint
			if (Grocycode::Validate($args['barcode'])) {
				$gc = new Grocycode($args['barcode']);
				if ($gc->GetExtraData() && !empty($gc->GetExtraData()[0])) {
					$args['entryId'] = $gc->GetExtraData()[0];
					return $this->InventoryStockEntry($request, $response, $args);
				}
			}

			$args['productId'] = $this->getStockService()->GetProductIdFromBarcode($args['barcode']);
			return $this->InventoryProduct($request, $response, $args);
		} catch (\Exception $ex) {
			$this->logFailedBarcodeScan($request, $args['barcode'], 'inventory', $ex->getMessage());
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function OpenProduct(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_OPEN);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('amount', $requestBody)) {
				throw new \Exception('An amount is required');
			}

			$specificStockEntryId = 'default';
			if (array_key_exists('stock_entry_id', $requestBody) && !empty($requestBody['stock_entry_id'])) {
				$specificStockEntryId = $requestBody['stock_entry_id'];
			}

			$allowSubproductSubstitution = false;
			if (array_key_exists('allow_subproduct_substitution', $requestBody)) {
				$allowSubproductSubstitution = $requestBody['allow_subproduct_substitution'];
			}

			$transactionId = null;
			$transactionId = $this->getStockService()->OpenProduct($args['productId'], $requestBody['amount'], $specificStockEntryId, $transactionId, $allowSubproductSubstitution);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function OpenProductByBarcode(Request $request, Response $response, array $args)
	{
		try {
			// Check if this is a Grocycode with stock entry ID - redirect to stock entry endpoint
			if (Grocycode::Validate($args['barcode'])) {
				$gc = new Grocycode($args['barcode']);
				if ($gc->GetExtraData() && !empty($gc->GetExtraData()[0])) {
					$args['entryId'] = $gc->GetExtraData()[0];
					return $this->OpenStockEntry($request, $response, $args);
				}
			}

			$args['productId'] = $this->getStockService()->GetProductIdFromBarcode($args['barcode']);

			// If no amount provided, use the configured barcode amount
			$requestBody = $request->getParsedBody();
			if ($requestBody === null || !array_key_exists('amount', $requestBody) || empty($requestBody['amount'])) {
				$defaultAmount = $this->getStockService()->GetBarcodeAmount($args['barcode']);
				if ($requestBody === null) {
					$requestBody = [];
				}
				$requestBody['amount'] = $defaultAmount;
				$request = $request->withParsedBody($requestBody);
			}

			return $this->OpenProduct($request, $response, $args);
		} catch (\Exception $ex) {
			$this->logFailedBarcodeScan($request, $args['barcode'], 'open', $ex->getMessage());
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ProductDetails(Request $request, Response $response, array $args)
	{
		try {
			return $this->ApiResponse($response, $this->getStockService()->GetProductDetails($args['productId']));
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ProductDetailsByBarcode(Request $request, Response $response, array $args)
	{
		try {
			$productId = $this->getStockService()->GetProductIdFromBarcode($args['barcode']);
			return $this->ApiResponse($response, $this->getStockService()->GetProductDetails($productId));
		} catch (\Exception $ex) {
			$this->logFailedBarcodeScan($request, $args['barcode'], 'details', $ex->getMessage());
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ProductBarcodesByUserfield(Request $request, Response $response, array $args)
	{
		try {
			return $this->ApiResponse(
				$response,
				$this->getStockService()->GetBarcodesByUserfield($args['userfieldName'], $args['userfieldValue'])
			);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ProductPriceHistory(Request $request, Response $response, array $args)
	{
		try {
			return $this->ApiResponse($response, $this->getStockService()->GetProductPriceHistory($args['productId']));
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ProductStockEntries(Request $request, Response $response, array $args)
	{
		$allowSubproductSubstitution = false;
		if (isset($request->getQueryParams()['include_sub_products']) && filter_var($request->getQueryParams()['include_sub_products'], FILTER_VALIDATE_BOOLEAN) !== false) {
			$allowSubproductSubstitution = true;
		}

		return $this->FilteredApiResponse($response, $this->getStockService()->GetProductStockEntries($args['productId'], false, $allowSubproductSubstitution), $request->getQueryParams());
	}

	public function LocationStockEntries(Request $request, Response $response, array $args)
	{
		return $this->FilteredApiResponse($response, $this->getStockService()->GetLocationStockEntries($args['locationId']), $request->getQueryParams());
	}

	public function ProductStockLocations(Request $request, Response $response, array $args)
	{
		$allowSubproductSubstitution = false;
		if (isset($request->getQueryParams()['include_sub_products']) && filter_var($request->getQueryParams()['include_sub_products'], FILTER_VALIDATE_BOOLEAN) !== false) {
			$allowSubproductSubstitution = true;
		}

		return $this->FilteredApiResponse($response, $this->getStockService()->GetProductStockLocations($args['productId'], $allowSubproductSubstitution), $request->getQueryParams());
	}

	public function ProductPrintLabel(Request $request, Response $response, array $args)
	{
		try {
			$productDetails = (object) $this->getStockService()->GetProductDetails($args['productId']);

			$webhookData = array_merge([
				'product' => $productDetails->product->name,
				'grocycode' => (string) (new Grocycode(Grocycode::PRODUCT, $productDetails->product->id)),
				'details' => $productDetails,
			], GROCY_LABEL_PRINTER_PARAMS);

			if (GROCY_LABEL_PRINTER_RUN_SERVER) {
				(new WebhookRunner())->run(GROCY_LABEL_PRINTER_WEBHOOK, $webhookData, GROCY_LABEL_PRINTER_HOOK_JSON);
			}

			return $this->ApiResponse($response, $webhookData);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function StockEntryPrintLabel(Request $request, Response $response, array $args)
	{
		try {
			$stockEntry = $this->getStockService()->GetStockEntry($args['entryId']);

			if ($stockEntry == null)
			{
				throw new \Exception('Stock entry not found');
			}

			$productDetails = (object) $this->getStockService()->GetProductDetails($stockEntry->product_id);
			$stockEntryUserfields = $this->getUserfieldsService()->GetValues('stock', $stockEntry->stock_id);

			$webhookData = array_merge([
				'product' => $productDetails->product->name,
				'grocycode' => (string) (new Grocycode(Grocycode::PRODUCT, $stockEntry->product_id, [$stockEntry->stock_id])),
				'details' => $productDetails,
				'stock_entry' => $stockEntry,
				'stock_entry_userfields' => $stockEntryUserfields,
			], GROCY_LABEL_PRINTER_PARAMS);

			if (GROCY_FEATURE_FLAG_STOCK_BEST_BEFORE_DATE_TRACKING) {
				$webhookData['due_date'] = $this->getLocalizationService()->__t('DD') . ': ' . $stockEntry->best_before_date;
			}

			if (GROCY_LABEL_PRINTER_RUN_SERVER) {
				(new WebhookRunner())->run(GROCY_LABEL_PRINTER_WEBHOOK, $webhookData, GROCY_LABEL_PRINTER_HOOK_JSON);
			}

			return $this->ApiResponse($response, $webhookData);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function RemoveProductFromShoppingList(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_SHOPPINGLIST_ITEMS_DELETE);

		try {
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$listId = 1;
			$amount = 1;
			$productId = null;

			if (array_key_exists('list_id', $requestBody) && !empty($requestBody['list_id']) && is_numeric($requestBody['list_id'])) {
				$listId = intval($requestBody['list_id']);
			}

			if (array_key_exists('product_amount', $requestBody) && !empty($requestBody['product_amount']) && is_numeric($requestBody['product_amount'])) {
				$amount = floatval($requestBody['product_amount']);
			}

			if (array_key_exists('product_id', $requestBody) && !empty($requestBody['product_id']) && is_numeric($requestBody['product_id'])) {
				$productId = intval($requestBody['product_id']);
			}

			if ($productId == null) {
				throw new \Exception('No product id was supplied');
			}

			$this->getStockService()->RemoveProductFromShoppingList($productId, $amount, $listId);
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function StockBooking(Request $request, Response $response, array $args)
	{
		try {
			$stockLogRow = $this->getDatabase()->stock_log($args['bookingId']);

			if ($stockLogRow === null) {
				throw new \Exception('Stock booking does not exist');
			}

			return $this->ApiResponse($response, $stockLogRow);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function StockEntry(Request $request, Response $response, array $args)
	{
		return $this->ApiResponse($response, $this->getStockService()->GetStockEntry($args['entryId']));
	}

	public function StockTransactions(Request $request, Response $response, array $args)
	{
		try {
			$transactionRows = $this->getDatabase()->stock_log()->where('transaction_id = :1', $args['transactionId'])->fetchAll();
			if (count($transactionRows) === 0) {
				throw new \Exception('No transaction was found by the given transaction id');
			}

			return $this->ApiResponse($response, $transactionRows);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function TransferProduct(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_TRANSFER);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try {
			if ($requestBody === null) {
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			if (!array_key_exists('amount', $requestBody)) {
				throw new \Exception('An amount is required');
			}

			if (!array_key_exists('location_id_from', $requestBody)) {
				throw new \Exception('A transfer from location is required');
			}

			if (!array_key_exists('location_id_to', $requestBody)) {
				throw new \Exception('A transfer to location is required');
			}

			$specificStockEntryId = 'default';

			if (array_key_exists('stock_entry_id', $requestBody) && !empty($requestBody['stock_entry_id'])) {
				$specificStockEntryId = $requestBody['stock_entry_id'];
			}

			$transactionId = $this->getStockService()->TransferProduct($args['productId'], $requestBody['amount'], $requestBody['location_id_from'], $requestBody['location_id_to'], $specificStockEntryId);
			$args['transactionId'] = $transactionId;
			return $this->StockTransactions($request, $response, $args);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function TransferProductByBarcode(Request $request, Response $response, array $args)
	{
		try {
			// Check if this is a Grocycode with stock entry ID - redirect to stock entry endpoint
			if (Grocycode::Validate($args['barcode'])) {
				$gc = new Grocycode($args['barcode']);
				if ($gc->GetExtraData() && !empty($gc->GetExtraData()[0])) {
					$args['entryId'] = $gc->GetExtraData()[0];
					return $this->TransferStockEntry($request, $response, $args);
				}
			}

			$args['productId'] = $this->getStockService()->GetProductIdFromBarcode($args['barcode']);

			// If no amount provided, use the configured barcode amount
			$requestBody = $request->getParsedBody();
			if ($requestBody === null || !array_key_exists('amount', $requestBody) || empty($requestBody['amount'])) {
				$defaultAmount = $this->getStockService()->GetBarcodeAmount($args['barcode']);
				if ($requestBody === null) {
					$requestBody = [];
				}
				$requestBody['amount'] = $defaultAmount;
				$request = $request->withParsedBody($requestBody);
			}

			return $this->TransferProduct($request, $response, $args);
		} catch (\Exception $ex) {
			$this->logFailedBarcodeScan($request, $args['barcode'], 'transfer', $ex->getMessage());
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function UndoBooking(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_EDIT);

		try {
			$this->ApiResponse($response, $this->getStockService()->UndoBooking($args['bookingId']));
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function UndoTransaction(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_EDIT);

		try {
			$this->ApiResponse($response, $this->getStockService()->UndoTransaction($args['transactionId']));
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function MergeProducts(Request $request, Response $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_STOCK_EDIT);

		try {
			if (filter_var($args['productIdToKeep'], FILTER_VALIDATE_INT) === false || filter_var($args['productIdToRemove'], FILTER_VALIDATE_INT) === false) {
				throw new \Exception('Provided {productIdToKeep} or {productIdToRemove} is not a valid integer');
			}

			$this->ApiResponse($response, $this->getStockService()->MergeProducts($args['productIdToKeep'], $args['productIdToRemove']));
			return $this->EmptyApiResponse($response);
		} catch (\Exception $ex) {
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
