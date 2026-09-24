@php require_frontend_packages(['datatables']); @endphp

@extends('layout.default')

@section('title', $__t('Pending Scans'))

@section('content')
<div class="row">
	<div class="col">
		<div class="title-related-links">
			<h2 class="title">@yield('title')</h2>
			<div class="float-right">
				<button class="btn btn-outline-dark d-md-none mt-2 order-1 order-md-3"
					type="button"
					data-toggle="collapse"
					data-target="#table-filter-row">
					<i class="fa-solid fa-filter"></i>
				</button>
			</div>
		</div>
	</div>
</div>

<hr class="my-2">

<div class="row collapse d-md-flex"
	id="table-filter-row">
	<div class="col-12 col-md-6 col-xl-3">
		<div class="input-group">
			<div class="input-group-prepend">
				<span class="input-group-text"><i class="fa-solid fa-search"></i></span>
			</div>
			<input type="text"
				id="search"
				class="form-control"
				placeholder="{{ $__t('Search') }}">
		</div>
	</div>
	<div class="col">
		<div class="float-right">
			<button id="clear-filter-button"
				class="btn btn-sm btn-outline-info"
				data-toggle="tooltip"
				title="{{ $__t('Clear filter') }}">
				<i class="fa-solid fa-filter-circle-xmark"></i>
			</button>
		</div>
	</div>
</div>

<div class="row">
	<div class="col">
		<table id="pending-scans-table"
			class="table table-sm table-striped nowrap w-100">
			<thead>
				<tr>
					<th class="border-right"><a class="text-muted change-table-columns-visibility-button"
							data-toggle="tooltip"
							title="{{ $__t('Table options') }}"
							data-table-selector="#pending-scans-table"
							href="#"><i class="fa-solid fa-eye"></i></a>
					</th>
					<th>{{ $__t('Created') }}</th>
					<th>{{ $__t('Barcode') }}</th>
					<th>{{ $__t('Operation') }}</th>
					<th>{{ $__t('Error') }}</th>
					<th>{{ $__t('Status') }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach($pendingScans as $scan)
				<tr>
					<td class="fit-content border-right">
						<a class="btn btn-info btn-sm"
							href="{{ $U('/pendingscan/') }}{{ $scan->id }}"
							data-toggle="tooltip"
							title="{{ $__t('View details') }}">
							<i class="fa-solid fa-eye"></i>
						</a>
						@if(!$scan->resolved)
						<button class="btn btn-success btn-sm resolve-scan-button"
							data-scan-id="{{ $scan->id }}"
							data-toggle="tooltip"
							title="{{ $__t('Mark as resolved') }}">
							<i class="fa-solid fa-check"></i>
						</button>
						@endif
					</td>
					<td>
						{{ $scan->row_created_timestamp }}
					</td>
					<td>
						<code>{{ $scan->barcode }}</code>
					</td>
					<td>
						@php
							$operationBadgeClass = match($scan->operation) {
								'add' => 'badge-success',      // green like purchase in livescreen
								'consume' => 'badge-danger',   // red like consume in livescreen
								'transfer' => 'badge-warning', // yellow like transfer in livescreen
								'inventory' => 'badge-success', // green like purchase in livescreen
								'open' => 'badge-primary',     // blue like product-opened in livescreen
								'details' => 'badge-info',
								default => 'badge-secondary'
							};
						@endphp
						<span class="badge {{ $operationBadgeClass }}">{{ ucfirst($scan->operation) }}</span>
					</td>
					<td class="text-truncate" style="max-width: 200px;">
						{{ $scan->error_message }}
					</td>
					<td>
						@if($scan->resolved)
						<span class="badge badge-success">{{ $__t('Resolved') }}</span>
						@else
						<span class="badge badge-warning">{{ $__t('Pending') }}</span>
						@endif
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var table = $('#pending-scans-table').DataTable({
		order: [[5, 'asc'], [1, 'desc']], // Status first (pending before resolved), then Created (newest first)
		columnDefs: [
			{ orderable: false, targets: 0 }
		]
	});

	// Connect search input to DataTable
	$('#search').on('keyup', function() {
		table.search(this.value).draw();
	});

	// Clear filter button functionality
	$('#clear-filter-button').on('click', function() {
		$('#search').val('');
		table.search('').draw();
	});

	$(document).on('click', '.resolve-scan-button', function() {
		const scanId = $(this).data('scan-id');

		if (confirm('{{ $__t('Are you sure you want to mark this scan as resolved?') }}')) {
			fetch(`/api/pending-scans/${scanId}/resolve`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				}
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					location.reload();
				} else {
					alert('{{ $__t('An error occurred') }}');
				}
			})
			.catch(error => {
				alert('{{ $__t('An error occurred') }}');
			});
		}
	});

	$(document).on('click', '.delete-scan-button', function() {
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
					location.reload();
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
