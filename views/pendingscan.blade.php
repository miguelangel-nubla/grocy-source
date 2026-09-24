@extends('layout.default')

@section('title', $__t('Pending Scan Details'))

@section('content')
<div class="row">
	<div class="col">
		<div class="title-related-links">
			<h2 class="title">@yield('title')</h2>
			<div class="float-right">
				<a class="btn btn-outline-secondary" href="{{ $U('/pendingscans') }}">
					<i class="fa-solid fa-arrow-left"></i> {{ $__t('Back to list') }}
				</a>
			</div>
		</div>
	</div>
</div>

<hr class="my-1">

<div class="row">
	{{-- Left column for desktop (xl-8), second on mobile --}}
	<div class="col-12 col-xl-8 order-2 order-xl-1 d-flex flex-column">

		{{-- Scan Information (desktop: top, mobile: bottom defaults to order-2) --}}
		<div class="card mb-2 order-2 order-xl-1">
			<div class="card-header py-1">
				<h6 class="card-title mb-0">{{ $__t('Scan Information') }}</h6>
			</div>
			<div class="card-body p-2">
				<dl class="row mb-0 small">
					<dt class="col-5 col-sm-4 mb-1">{{ $__t('ID') }}</dt>
					<dd class="col-7 col-sm-8 mb-1">#{{ $pendingScan->id }}</dd>
					<dt class="col-5 col-sm-4 mb-1">{{ $__t('Created') }}</dt>
					<dd class="col-7 col-sm-8 mb-1">{{ $pendingScan->row_created_timestamp }}</dd>
					<dt class="col-5 col-sm-4 mb-1">{{ $__t('Barcode') }}</dt>
					<dd class="col-7 col-sm-8 mb-1"><code>{{ $pendingScan->barcode }}</code></dd>
					<dt class="col-5 col-sm-4 mb-1">{{ $__t('Operation') }}</dt>
					@php
						$operationBadgeClass = match($pendingScan->operation) {
							'add' => 'badge-success',
							'consume' => 'badge-danger',
							'transfer' => 'badge-warning',
							'inventory' => 'badge-success',
							'open' => 'badge-primary',
							'details' => 'badge-info',
							default => 'badge-secondary'
						};
					@endphp
					<dd class="col-7 col-sm-8 mb-1"><span
							class="badge {{ $operationBadgeClass }}">{{ ucfirst($pendingScan->operation) }}</span></dd>
					<dt class="col-5 col-sm-4 mb-1">{{ $__t('Status') }}</dt>
					<dd class="col-7 col-sm-8 mb-1">
						@if($pendingScan->resolved)
							<span class="badge badge-success">{{ $__t('Resolved') }}</span>
							@if($pendingScan->resolved_timestamp)
								<small class="text-muted ml-1">{{ $pendingScan->resolved_timestamp }}</small>
							@endif
						@else
							<span class="badge badge-warning">{{ $__t('Pending') }}</span>
						@endif
					</dd>
					@if($pendingScan->user_agent)
						<dt class="col-5 col-sm-4 mb-1">{{ $__t('User Agent') }}</dt>
						<dd class="col-7 col-sm-8 mb-1"><small>{{ $pendingScan->user_agent }}</small></dd>
					@endif
					@if($pendingScan->ip_address)
						<dt class="col-5 col-sm-4 mb-0">{{ $__t('IP Address') }}</dt>
						<dd class="col-7 col-sm-8 mb-0"><code>{{ $pendingScan->ip_address }}</code></dd>
					@endif
				</dl>
			</div>
		</div>

		{{-- Error Details (desktop: bottom, mobile: top defaults to order-1) --}}
		<div class="card mb-2 order-1 order-xl-2">
			<div class="card-header py-1">
				<h6 class="card-title mb-0">{{ $__t('Error Details') }}</h6>
			</div>
			<div class="card-body p-2">
				<div class="alert alert-danger py-1 px-2 mb-2 small">
					<strong>{{ $__t('Error Message') }}:</strong> {{ $pendingScan->error_message }}
				</div>

				@if($requestData)
					<h6>{{ $__t('Request Data') }}</h6>
					@if(isset($requestData['method']))
						<div class="p-2 bg-light rounded border mb-2 small">
							<strong class="text-dark">{{ $__t('Original Request') }}:</strong><br>
							<small class="text-monospace text-dark">{{ $requestData['method'] }}
								{{ $requestData['path'] }}</small>
							@if(!empty($requestData['query_params']))
								<br><small class="text-secondary">{{ $__t('Query params') }}:
									{{ http_build_query($requestData['query_params']) }}</small>
							@endif
						</div>
					@endif
					@if(isset($requestData['body']) && !empty($requestData['body']))
						<strong>{{ $__t('Request Body') }}:</strong>
						<pre
							class="bg-light p-2 rounded"><code>{{ json_encode($requestData['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
					@endif

					@if(isset($requestData['query_params']) && !empty($requestData['query_params']))
						<strong>{{ $__t('Query Parameters') }}:</strong>
						<pre
							class="bg-light p-2 rounded"><code>{{ json_encode($requestData['query_params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
					@endif
				@endif
			</div>
		</div>

	</div>

	{{-- Right column for desktop (xl-4), first on mobile --}}
	<div class="col-12 col-xl-4 order-1 order-xl-2 d-flex flex-column">

		{{-- External barcode lookup --}}
		@if(!empty($pendingScan->barcode))
		@php $encodedBarcode = rawurlencode($pendingScan->barcode); @endphp
		<div class="card mb-2">
			<div class="card-header py-1">
				<h6 class="card-title mb-0">{{ $__t('External barcode lookup') }}</h6>
			</div>
			<div class="card-body p-2">
				<div id="external-barcode-lookup" class="barcode-lookup" aria-live="polite"
					data-barcode="{{ $pendingScan->barcode }}"
					data-not-found-message="{{ $__t('No product data was found for this barcode.') }}"
					data-error-message="{{ $__t('Barcode information could not be loaded.') }}"
					data-not-available-label="{{ $__t('Not available') }}">
					<div style="min-height: 80px;">
						<div class="barcode-loading d-flex align-items-center text-muted small py-1 mb-1 border-bottom">
							<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
							<span>{{ $__t('Loading barcode information...') }}</span>
						</div>
						<div class="barcode-error alert alert-warning small d-none mb-1 py-1 px-2" role="alert"></div>
						<div class="barcode-result d-none pt-1">
							<div class="media align-items-center align-items-md-start">
								<img class="barcode-result-image rounded mr-2 mb-2 mb-md-0 d-none" data-lookup-image
									alt="{{ $__t('Product image') }}" width="64" height="64"
									style="width: 64px; height: 64px; object-fit: contain;">
								<div class="media-body">
									<h6 class="mb-0 font-weight-bold" data-lookup-field="name">
										{{ $__t('Not available') }}
									</h6>
									<div class="small text-muted d-none" data-field-wrapper="brands">
										<span class="text-uppercase font-weight-bold mr-1">{{ $__t('Brand') }}</span>
										<span data-lookup-field="brands"></span>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="mt-2">
						<div class="d-flex flex-wrap">
							<a class="btn btn-outline-secondary btn-sm mb-1 mr-1"
								href="https://www.google.com/search?q={{ $encodedBarcode }}" target="_blank"
								rel="noopener">
								<i class="fa-solid fa-arrow-up-right-from-square"></i> {{ $__t('Google') }}
							</a>
							<a class="btn btn-outline-secondary btn-sm mb-1 mr-1"
								href="https://world.openfoodfacts.org/product/{{ $encodedBarcode }}" target="_blank"
								rel="noopener">
								<i class="fa-solid fa-arrow-up-right-from-square"></i>
								{{ $__t('OpenFoodFacts') }}
							</a>
							<a class="btn btn-outline-secondary btn-sm mb-1"
								href="https://www.barcodelookup.com/{{ $encodedBarcode }}" target="_blank"
								rel="noopener">
								<i class="fa-solid fa-arrow-up-right-from-square"></i> {{ $__t('BarcodeLookup') }}
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		@endif

		{{-- Scan Actions --}}
		<div class="card mb-2">
			<div class="card-header py-1">
				<h6 class="card-title mb-0">{{ $__t('Scan Actions') }}</h6>
			</div>
			<div class="card-body p-2">
				<div class="d-flex">
					<button class="btn btn-success btn-sm mr-2 flex-grow-1 resolve-scan-button"
						data-scan-id="{{ $pendingScan->id }}">
						<i class="fa-solid fa-check"></i> {{ $__t('Mark as Resolved') }}
					</button>
					<button class="btn btn-danger btn-sm delete-scan-button" data-scan-id="{{ $pendingScan->id }}">
						<i class="fa-solid fa-trash"></i> {{ $__t('Delete') }}
					</button>
				</div>
			</div>
		</div>

		{{-- Quick Actions --}}
		<div class="card mb-2">
			<div class="card-header py-1">
				<h6 class="card-title mb-0">{{ $__t('Quick Actions') }}</h6>
			</div>
			<div class="card-body p-2">
				@if(str_contains($pendingScan->error_message, 'No product with barcode') || empty($pendingScan->error_message) || stripos($pendingScan->error_message, 'not found') !== false)
					@php
						$pendingScanUrl = '/pendingscan/' . $pendingScan->id;
						$targetUrl = '';
						switch ($pendingScan->operation) {
							case 'add':
								$targetUrl = '/purchase?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl);
								break;
							case 'consume':
							case 'open':
								$targetUrl = '/consume?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl);
								break;
							case 'transfer':
								$targetUrl = '/transfer?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl);
								break;
							case 'inventory':
								$targetUrl = '/inventory?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl);
								break;
							case 'details':
							default:
								$targetUrl = '/purchase?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl);
								break;
						}
					@endphp
					<a class="btn btn-primary btn-sm mb-1 d-block" href="{{ $U($targetUrl) }}">
						<i class="fa-solid fa-barcode"></i> {{ $__t('Add Barcode to Existing Product') }}
					</a>

					@if($pendingScan->operation === 'details')
						<a class="btn btn-outline-primary btn-sm mb-1 d-block"
							href="{{ $U('/inventory?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl)) }}">
							<i class="fa-solid fa-list"></i> {{ $__t('Add Barcode to Existing Product') }} ({{ $__t('Inventory') }})
						</a>
						<a class="btn btn-outline-primary btn-sm mb-1 d-block"
							href="{{ $U('/consume?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl)) }}">
							<i class="fa-solid fa-utensils"></i> {{ $__t('Add Barcode to Existing Product') }} ({{ $__t('Consume') }})
						</a>
					@endif

					@php
						switch ($pendingScan->operation) {
							case 'consume':
							case 'open':
								$targetUrl = '/inventory?flow=InplaceAddBarcodeToExistingProduct&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($pendingScanUrl);
								break;
							default:
								break;
						}
						// Build create product return URL from inside out:
						$createProductUrl = '/product/new?flow=InplaceNewProductWithBarcode&barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode($targetUrl);
					@endphp
					<a class="btn btn-outline-primary btn-sm mb-1 d-block" href="{{ $U($createProductUrl) }}">
						<i class="fa-solid fa-plus"></i> {{ $__t('Create New Product') }}
					</a>
				@endif

				@if($pendingScan->operation === 'add')
					<a class="btn btn-info btn-sm mb-1 d-block"
						href="{{ $U('/purchase?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-cart-plus"></i> {{ $__t('Add Stock (Purchase)') }}
					</a>
				@endif

				@if($pendingScan->operation === 'consume')
					<a class="btn btn-info btn-sm mb-1 d-block"
						href="{{ $U('/consume?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-utensils"></i> {{ $__t('Consume Stock') }}
					</a>
				@endif

				@if($pendingScan->operation === 'transfer')
					<a class="btn btn-info btn-sm mb-1 d-block"
						href="{{ $U('/transfer?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-exchange-alt"></i> {{ $__t('Transfer Stock') }}
					</a>
				@endif

				@if($pendingScan->operation === 'open')
					<a class="btn btn-info btn-sm mb-1 d-block"
						href="{{ $U('/consume?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-box-open"></i> {{ $__t('Open Product') }}
					</a>
				@endif

				@if($pendingScan->operation === 'inventory')
					<a class="btn btn-warning btn-sm mb-1 d-block"
						href="{{ $U('/inventory?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-list"></i> {{ $__t('Inventory Management') }}
					</a>
				@endif

				@if($pendingScan->operation === 'details')
					<a class="btn btn-info btn-sm mb-1 d-block"
						href="{{ $U('/purchase?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-cart-plus"></i> {{ $__t('Add Stock (Purchase)') }}
					</a>
					<a class="btn btn-info btn-sm mb-1 d-block"
						href="{{ $U('/consume?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-utensils"></i> {{ $__t('Consume Stock') }}
					</a>
					<a class="btn btn-warning btn-sm mb-1 d-block"
						href="{{ $U('/inventory?barcode=' . urlencode($pendingScan->barcode) . '&returnto=' . urlencode('/pendingscan/' . $pendingScan->id)) }}">
						<i class="fa-solid fa-list"></i> {{ $__t('Inventory Management') }}
					</a>
				@endif
			</div>
		</div>

	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		const barcodeLookup = document.getElementById('external-barcode-lookup');
		if (barcodeLookup && barcodeLookup.dataset.barcode) {
			const barcode = barcodeLookup.dataset.barcode;
			const loading = barcodeLookup.querySelector('.barcode-loading');
			const result = barcodeLookup.querySelector('.barcode-result');
			const error = barcodeLookup.querySelector('.barcode-error');
			const notFoundMessage = barcodeLookup.dataset.notFoundMessage;
			const errorMessage = barcodeLookup.dataset.errorMessage;
			const notAvailableLabel = barcodeLookup.dataset.notAvailableLabel;

			if (result) {
				const finishLoading = () => {
					if (!loading) {
						return;
					}

					if (typeof loading.remove === 'function') {
						loading.remove();
					}
					else if (loading.parentNode) {
						loading.parentNode.removeChild(loading);
					}
					else {
						loading.classList.add('d-none');
					}
				};

				const setFieldText = (field, value) => {
					const fieldElement = result.querySelector(`[data-lookup-field="${field}"]`);
					const wrapper = result.querySelector(`[data-field-wrapper="${field}"]`);

					if (!fieldElement) {
						return;
					}

					let trimmedValue = '';
					if (value !== undefined && value !== null) {
						trimmedValue = value.toString().trim();
					}

					if (trimmedValue !== '') {
						fieldElement.textContent = trimmedValue;
						if (wrapper) {
							wrapper.classList.remove('d-none');
						}
					}
					else {
						fieldElement.textContent = notAvailableLabel;
						if (wrapper) {
							wrapper.classList.add('d-none');
						}
					}
				};

				const toggleImage = (url, altText) => {
					const imageElement = result.querySelector('[data-lookup-image]');

					if (!imageElement) {
						return;
					}

					if (url && url.trim() !== '') {
						imageElement.src = url;
						imageElement.alt = altText || imageElement.alt;
						imageElement.classList.remove('d-none');
					}
					else {
						imageElement.classList.add('d-none');
						imageElement.removeAttribute('src');
					}
				};

				if (typeof fetch !== 'function') {
					finishLoading();

					if (error) {
						error.textContent = errorMessage;
						error.classList.remove('d-none');
					}

					return;
				}

				fetch('https://world.openfoodfacts.org/api/v2/product/' + encodeURIComponent(barcode) + '.json')
					.then(response => {
						if (!response.ok) {
							throw new Error('network-error');
						}

						return response.json();
					})
					.then(data => {
						if (!data || data.status !== 1 || !data.product) {
							throw new Error('not-found');
						}

						const product = data.product;

						finishLoading();

						if (error) {
							error.classList.add('d-none');
							error.textContent = '';
						}

						result.classList.remove('d-none');

						const productName = (product.product_name || product.generic_name || '').trim();
						const brands = (product.brands || '').trim();
						setFieldText('name', productName || barcode);
						setFieldText('brands', brands);

						const imageUrl = product.image_front_small_url || product.image_small_url || product.image_thumb_url;
						toggleImage(imageUrl, productName || barcode);
					})
					.catch(errorObj => {
						finishLoading();

						if (error) {
							error.textContent = errorObj && errorObj.message === 'not-found' ? notFoundMessage : errorMessage;
							error.classList.remove('d-none');
						}
					});
			}
		}

		$(document).on('click', '.resolve-scan-button', function () {
			const scanId = $(this).data('scan-id');

			fetch(`/api/pending-scans/${scanId}/resolve`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				}
			})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						window.location.href = '{{ $U('/pendingscans') }}';
					} else {
						alert('{{ $__t('An error occurred') }}');
					}
				})
				.catch(error => {
					alert('{{ $__t('An error occurred') }}');
				});
		});

		$(document).on('click', '.delete-scan-button', function () {
			const scanId = $(this).data('scan-id');

			if (confirm('{{ $__t('Are you sure you want to delete this scan?') }}')) {
				fetch(`/api/pending-scans/${scanId}`, {
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
					}
				})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							window.location.href = '{{ $U('/pendingscans') }}';
						} else {
							alert('{{ $__t('An error occurred') }}');
						}
					})
					.catch(error => {
						alert('{{ $__t('An error occurred') }}');
					});
			}
		});
	});
</script>
@stop