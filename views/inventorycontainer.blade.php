@extends('layout.default')

@section('title', $__t('Inventory container'))

@section('content')
<script>
	Grocy.QuantityUnits = {!! json_encode($quantityUnits) !!};
	Grocy.ContainerWeightUserfield = {!! json_encode($container_weight_userfield) !!};
	Grocy.WeightPrecisionTolerance = {!! json_encode($weight_precision_tolerance) !!};
</script>

<div class="row">
	<div class="col-12 col-md-6 col-xl-4 pb-3">


		<hr class="my-2">



		<form id="container-inventory-form" novalidate>

			<div class="form-group">
				<label class="w-100" for="container_scanner">{{ $__t('Container barcode') }}
					<span class="title">
						<i class="fa-solid fa-question-circle text-muted small" data-toggle="tooltip"
							title="{{ $__t('Scan the barcode on your container to perform inventory by container weight. Each container can have its own tare weight set via stock entry user fields.') }}"></i>
					</span>
					<i id="barcode-lookup-hint" class="fa-solid fa-barcode float-right mt-1"></i>
				</label>
				<div class="input-group">
					<div class="input-group-prepend">
						<button id="clear-container-scanner" class="btn btn-outline-secondary" type="button"
							data-toggle="tooltip" title="{{ $__t('Clear') }}">
							<i class="fa-solid fa-times"></i>
						</button>
					</div>
					<input type="text" class="form-control barcodescanner-input" id="container_scanner"
						name="container_tag" data-target="#container_scanner" autocomplete="off">
				</div>
				<div class="invalid-feedback"></div>
			</div>

			<div id="container-details" class="d-none">
				<div class="card mb-1">
					<div class="card-header py-1 d-flex justify-content-between align-items-center">
						<h5 class="card-title mb-0">{{ $__t('Container details') }}</h5>
						<div id="container-actions" class="d-none">
							<a class="btn btn-danger btn-sm container-consume-button" href="#" data-toggle="tooltip"
								title="{{ $__t('Consume this stock entry') }}">
								<i class="fa-solid fa-utensils"></i>
							</a>
							<a class="btn btn-info btn-sm show-as-dialog-link stock-edit-button" href="#"
								data-toggle="tooltip" title="{{ $__t('Edit stock entry') }}">
								<i class="fa-solid fa-edit"></i>
							</a>
							<button class="btn btn-sm btn-light text-secondary container-context-menu-button"
								type="button">
								<i class="fa-solid fa-ellipsis-v"></i>
							</button>
						</div>
					</div>
					<div class="card-body p-1">
						<div class="row no-gutters">
							<div class="col-6 mb-1 px-1">
								<small class="text-muted d-block">{{ $__t('Product') }}</small>
								<div class="font-weight-bold small" id="product_name"></div>
							</div>
							<div class="col-6 mb-1 px-1">
								<small class="text-muted d-block">{{ $__t('Current amount') }}</small>
								<div class="font-weight-bold small" id="current_amount"></div>
							</div>
							<div class="col-6 mb-1 px-1">
								<small class="text-muted d-block">{{ $__t('Location') }}</small>
								<div class="font-weight-bold small" id="location_name"></div>
							</div>
							<div class="col-6 mb-1 px-1">
								<small class="text-muted d-block">{{ $__t('Container weight') }}</small>
								<div class="font-weight-bold small" id="container_weight"></div>
							</div>
							<div class="col-12 px-1">
								<small class="text-muted d-block">{{ $__t('Best before') }}</small>
								<div class="font-weight-bold small" id="best_before_date"></div>
							</div>
						</div>
					</div>
				</div>


				<div class="form-group">
					<div class="form-row mb-1">
						<div class="col-6">
							<label for="gross_weight">{{ $__t('Weight | Net | Difference') }} <i
									class="fa-solid fa-question-circle text-muted" data-toggle="tooltip"
									title="{{ $__t('Total weight including container') }}"></i></label>
						</div>
					</div>

					<div class="input-group">
						<input type="number" class="form-control locale-number-input" id="gross_weight" name="gross_weight"
							step="{{ $weight_precision_tolerance }}" min="0" placeholder="0">
						<input type="text" class="form-control" id="net_weight" readonly style="cursor: not-allowed;"
							tabindex="-1">
						<div class="input-group-append">
							<span class="input-group-text" id="gross_weight_unit"></span>
							<span class="input-group-text d-none order-first" id="net_weight_diff"></span>
						</div>
					</div>
					<div class="invalid-feedback"></div>
				</div>
			</div>

			<!-- Stock source/destination selection (hidden by default) -->
			<div id="stock-source-group" class="form-group d-none">
				<label for="source_stock_entry">{{ $__t('Stock source') }}</label>
				<select class="form-control" id="source_stock_entry" name="source_stock_entry">
					<option value="">{{ $__t('Select where this stock came from') }}</option>
				</select>
				<small class="form-text text-muted">
					{{ $__t('Select the stock entry that this additional stock was transferred from') }}
				</small>
				<div class="invalid-feedback"></div>

				<!-- Partial transfer info -->
				<div id="partial-transfer-info" class="alert alert-warning mt-2 d-none">
					<i class="fa-solid fa-exclamation-triangle"></i>
					<span id="partial-transfer-message"></span>
				</div>

				<!-- Option to allow remaining stock modification -->
				<div id="stock-modification-option" class="mt-2 d-none">
					<div class="custom-control custom-radio">
						<input class="custom-control-input" type="radio" name="partial_transfer_mode" id="add_remaining"
							value="add_remaining">
						<label class="custom-control-label" for="add_remaining">
							{{ $__t('Add remaining stock without source') }}
						</label>
					</div>
					<div class="custom-control custom-radio">
						<input class="custom-control-input" type="radio" name="partial_transfer_mode"
							id="transfer_available_only" value="transfer_available_only">
						<label class="custom-control-label" for="transfer_available_only">
							{{ $__t('Only transfer what is available (adjust inventory to match)') }}
						</label>
					</div>
					<small class="form-text text-muted">
						<span id="remaining-modification-details"></span>
					</small>
				</div>
			</div>

			<div id="stock-destination-group" class="form-group d-none">
				<label for="destination_type">{{ $__t('Stock destination') }} <span class="text-danger">*</span></label>
				<div class="custom-control custom-radio">
					<input class="custom-control-input" type="radio" name="destination_type" id="destination_consume"
						value="consume" checked>
					<label class="custom-control-label" for="destination_consume">
						{{ $__t('Consumed') }}
					</label>
				</div>
				<div class="custom-control custom-radio">
					<input class="custom-control-input" type="radio" name="destination_type" id="destination_transfer"
						value="transfer">
					<label class="custom-control-label" for="destination_transfer">
						{{ $__t('Transferred to another stock entry') }}
					</label>
				</div>

				<div id="destination-stock-entry" class="mt-2 d-none">
					<select class="form-control" id="destination_stock_entry" name="destination_stock_entry">
						<option value="">{{ $__t('Select destination stock entry') }}</option>
					</select>
				</div>
				<div class="invalid-feedback"></div>
			</div>

			<button id="save-inventory" class="btn btn-success" type="submit">
				<i class="fa-solid fa-save"></i> {{ $__t('Save inventory') }}
			</button>

			<button id="clear-form" class="btn btn-secondary ml-2" type="button">
				<i class="fa-solid fa-trash"></i> {{ $__t('Clear') }}
			</button>
		</form>
	</div>

	<div class="col-12 col-md-6 col-xl-8 hide-when-embedded">
		@include('components.productcard')

		<div class="mt-4">
			<h2 class="title">{{ $__t('Recent container inventories') }}</h2>
			<div id="recent-inventories-table"></div>
		</div>
	</div>
</div>

<div class="modal fade" id="container-weight-modal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">{{ $__t('Container weight not set') }}</h5>
				<button type="button" class="close" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p>{{ $__t('This container does not have a container weight set. You need to set the container weight in the stock entry user fields before you can use container inventory.') }}
				</p>
				<p><strong>{{ $__t('Steps') }}:</strong></p>
				<ol>
					<li>{{ $__t('Close this modal') }}</li>
					<li>{{ $__t('Click the "Edit stock entry" button') }} <i class="fa-solid fa-edit"></i></li>
					<li>{{ $__t('Set the "Stock Entry Container Weight" user field') }}</li>
					<li>{{ $__t('Save and scan again') }}</li>
				</ol>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">{{ $__t('Close') }}</button>
			</div>
		</div>
	</div>
</div>

@include('components.camerabarcodescanner')



<div class="modal fade" id="stockentry-context-menu" tabindex="-1">
	<div class="modal-dialog modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">
					<span class="product-name"></span>
				</h5>
				<button type="button" class="close" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<div class="modal-body py-0">
				<div class="p-2">
					<div class="row no-gutters mb-2">
						@if(GROCY_FEATURE_FLAG_SHOPPINGLIST)
							<div class="col px-1">
								<a class="btn btn-outline-secondary btn-block show-as-dialog-link permission-SHOPPINGLIST_ITEMS_ADD modal-context-menu-button-compact context-menu-button-shopping-list"
									href="#"
									data-href-template="{{ $U('/shoppinglistitem/new?embedded&updateexistingproduct&list=1&product=') }}">
									<i class="fa-solid fa-shopping-cart"></i>
									<span>{{ $__t('Add to shopping list') }}</span>
								</a>
							</div>
						@endif
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block show-as-dialog-link permission-STOCK_PURCHASE modal-context-menu-button-compact context-menu-button-purchase"
								href="#" data-href-template="{{ $U('/purchase?embedded&product=') }}">
								<i class="fa-solid fa-cart-plus"></i> <span>{{ $__t('Purchase') }}</span>
							</a>
						</div>
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block show-as-dialog-link stock-consume-link permission-STOCK_CONSUME modal-context-menu-button-compact context-menu-button-consume"
								href="#">
								<i class="fa-solid fa-utensils"></i> <span>{{ $__t('Consume') }}</span>
							</a>
						</div>
						@if(GROCY_FEATURE_FLAG_STOCK_LOCATION_TRACKING)
							<div class="col px-1">
								<a class="btn btn-outline-secondary btn-block show-as-dialog-link stock-transfer-link permission-STOCK_TRANSFER modal-context-menu-button-compact context-menu-button-transfer"
									href="#">
									<i class="fa-solid fa-exchange-alt"></i> <span>{{ $__t('Transfer') }}</span>
								</a>
							</div>
						@endif
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block show-as-dialog-link permission-STOCK_INVENTORY modal-context-menu-button-compact context-menu-button-inventory"
								href="#" data-href-template="{{ $U('/inventory?embedded&product=') }}">
								<i class="fa-solid fa-list"></i> <span>{{ $__t('Inventory') }}</span>
							</a>
						</div>
					</div>

					<div class="row no-gutters mb-2">
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block container-consume-button container-consume-button-spoiled permission-STOCK_CONSUME modal-context-menu-button-compact context-menu-button-consume-spoiled"
								href="#" data-dismiss="modal">
								<i class="fa-solid fa-circle-xmark"></i> <span class="spoiled-text"></span>
							</a>
						</div>
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block productcard-trigger modal-context-menu-button-compact context-menu-button-info"
								href="#">
								<i class="fa-solid fa-info-circle"></i> <span>{{ $__t('Product overview') }}</span>
							</a>
						</div>
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block show-as-dialog-link modal-context-menu-button-compact context-menu-button-info"
								href="#" data-href-template="{{ $U('/stockjournal?embedded&product=') }}"
								data-dialog-type="table">
								<i class="fa-solid fa-clock-rotate-left"></i> <span>{{ $__t('Stock journal') }}</span>
							</a>
						</div>
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block show-as-dialog-link modal-context-menu-button-compact context-menu-button-info"
								href="#" data-href-template="{{ $U('/stockjournal/summary?embedded&product=') }}"
								data-dialog-type="table">
								<i class="fa-solid fa-table-list"></i> <span>{{ $__t('Stock journal summary') }}</span>
							</a>
						</div>
						@if(GROCY_FEATURE_FLAG_RECIPES)
							<div class="col px-1">
								<a class="btn btn-outline-secondary btn-block modal-context-menu-button-compact context-menu-button-info"
									href="#" data-href-template="{{ $U('/recipes?search=') }}">
									<i class="fa-solid fa-pizza-slice"></i>
									<span>{{ $__t('Search for recipes containing this product') }}</span>
								</a>
							</div>
						@endif
					</div>

					<div class="row no-gutters mb-2">
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block link-return permission-MASTER_DATA_EDIT modal-context-menu-button-compact context-menu-button-edit"
								href="#" data-href-template="{{ $U('/product/') }}">
								<i class="fa-solid fa-edit"></i> <span>{{ $__t('Edit product') }}</span>
							</a>
						</div>
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block stock-grocycode-link modal-context-menu-button-compact context-menu-button-download"
								href="#">
								<i class="fa-solid fa-barcode"></i>
								<span>{!! str_replace('Grocycode', '<span class="ls-n1">Grocycode</span>', $__t('Download %s Grocycode', $__t('Stock entry'))) !!}</span>
							</a>
						</div>
						@if(GROCY_FEATURE_FLAG_LABEL_PRINTER)
							<div class="col px-1">
								<a class="btn btn-outline-secondary btn-block stockentry-grocycode-label-print modal-context-menu-button-compact context-menu-button-print"
									href="#">
									<i class="fa-solid fa-print"></i>
									<span>{!! str_replace('Grocycode', '<span class="ls-n1">Grocycode</span>', $__t('Print %s Grocycode on label printer', $__t('Stock entry'))) !!}</span>
								</a>
							</div>
						@endif
						<div class="col px-1">
							<a class="btn btn-outline-secondary btn-block stockentry-label-link modal-context-menu-button-compact"
								target="_blank" href="#">
								<i class="fa-solid fa-arrow-up-right-from-square"></i>
								<span>{{ $__t('Open stock entry label in new window') }}</span>
							</a>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">{{ $__t('Close') }}</button>
			</div>
		</div>
	</div>
</div>

@include('components.productcard', [
	'asModal' => true
])

@stop