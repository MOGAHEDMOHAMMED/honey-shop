@extends('layouts.app')

@section('title', __('catalog.pages.sales'))
@section('heading', __('catalog.pages.sales'))
@section('breadcrumb', __('catalog.navigation.sales').' / '.__('catalog.pages.sales'))

@section('content')
<section class="card page-card"><div class="card-body">
    <form method="get" action="{{ route('sales.index') }}" class="row g-2 align-items-end mb-4">
        <div class="col-12 col-md-4">
            <label for="sales-search" class="form-label">{{ __('catalog.fields.search') }}</label>
            <input id="sales-search" name="search" value="{{ $search }}" class="form-control" maxlength="100">
        </div>
        <div class="col-12 col-md-3">
            <label for="sales-channel" class="form-label">{{ __('catalog.fields.sales_channel') }}</label>
            <select id="sales-channel" name="channel" class="form-select">
                <option value="">{{ __('catalog.fields.all_types') }}</option>
                <option value="direct" @selected(($filters['channel'] ?? '') === 'direct')>{{ __('catalog.fields.direct_sale') }}</option>
                <option value="external" @selected(($filters['channel'] ?? '') === 'external')>{{ __('catalog.fields.external_sale') }}</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="sales-status" class="form-label">{{ __('catalog.fields.status') }}</label>
            <select id="sales-status" name="status" class="form-select">
                <option value="">{{ __('catalog.fields.all_statuses') }}</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('catalog.statuses.draft') }}</option>
                <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>{{ __('catalog.statuses.approved') }}</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">{{ __('catalog.actions.search') }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('sales.index') }}">{{ __('catalog.actions.clear') }}</a>
        </div>
        @can('sales.manage')
            <div class="col-12 col-lg-auto me-lg-auto">
                <a class="btn btn-success" href="{{ route('sales.create') }}">{{ __('catalog.actions.create_sale') }}</a>
            </div>
        @endcan
    </form>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>{{ __('catalog.fields.invoice_number') }}</th>
                <th>{{ __('catalog.fields.customer') }}</th>
                <th>{{ __('catalog.fields.sales_channel') }}</th>
                <th>{{ __('catalog.fields.document_date') }}</th>
                <th>{{ __('catalog.fields.invoice_total') }}</th>
                <th>{{ __('catalog.fields.status') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($sales as $sale)
                <tr>
                    <td><a href="{{ route('sales.show', $sale) }}" class="text-decoration-none">{{ $sale->invoice_number }}</a></td>
                    <td>{{ $sale->customer->name }}</td>
                    <td>{{ __('catalog.fields.'.$sale->sales_channel.'_sale') }}</td>
                    <td>{{ $sale->businessTransaction->occurred_at->format('Y-m-d') }}</td>
                    <td dir="ltr">{{ \App\Support\Money::format($sale->final_total) }} {{ __('catalog.currency') }}</td>
                    <td><span class="badge {{ $sale->businessTransaction->status === 'approved' ? 'text-bg-success' : 'text-bg-warning' }}">
                        {{ __('catalog.statuses.'.$sale->businessTransaction->status) }}
                    </span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">{{ __('catalog.messages.no_sales') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $sales->links() }}</div>
</div></section>
@endsection
