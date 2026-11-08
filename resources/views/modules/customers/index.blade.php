@extends('layouts.app')

@section('title', __('catalog.pages.customers'))
@section('heading', __('catalog.pages.customers'))
@section('breadcrumb', __('catalog.navigation.sales').' / '.__('catalog.pages.customers'))

@section('content')
<section class="card page-card"><div class="card-body">
    <form method="get" action="{{ route('customers.index') }}" class="row g-2 align-items-end mb-4">
        <div class="col-12 col-md-5">
            <label for="customer-search" class="form-label">{{ __('catalog.fields.search') }}</label>
            <input id="customer-search" name="search" value="{{ $search }}" class="form-control" maxlength="100">
        </div>
        <div class="col-12 col-md-3">
            <label for="customer-status" class="form-label">{{ __('catalog.fields.status') }}</label>
            <select id="customer-status" name="status" class="form-select">
                <option value="">{{ __('catalog.fields.all_statuses') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('catalog.fields.active') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('catalog.fields.inactive') }}</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">{{ __('catalog.actions.search') }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('customers.index') }}">{{ __('catalog.actions.clear') }}</a>
        </div>
        @can('customers.manage')
            <div class="col-12 col-lg-auto me-lg-auto">
                <a class="btn btn-success" href="{{ route('customers.create') }}">{{ __('catalog.actions.create_customer') }}</a>
            </div>
        @endcan
    </form>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>{{ __('catalog.fields.customer_name') }}</th>
                <th>{{ __('catalog.fields.phone') }}</th>
                <th>{{ __('catalog.fields.customer_balance') }}</th>
                <th>{{ __('catalog.fields.sales_count') }}</th>
                <th>{{ __('catalog.fields.status') }}</th>
                <th>{{ __('catalog.fields.actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td><a class="fw-semibold text-decoration-none" href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                    <td dir="ltr">{{ $customer->phone ?: '—' }}</td>
                    <td dir="ltr">{{ \App\Support\Money::format((string) ($balances[$customer->id] ?? '0')) }} {{ __('catalog.currency') }}</td>
                    <td>{{ number_format($customer->sales_count) }}</td>
                    <td><span class="badge {{ $customer->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $customer->is_active ? __('catalog.fields.active') : __('catalog.fields.inactive') }}
                    </span></td>
                    <td><div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('customers.show', $customer) }}">{{ __('catalog.actions.view') }}</a>
                        @can('customers.manage')
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('customers.edit', $customer) }}">{{ __('catalog.actions.edit') }}</a>
                        @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">{{ __('catalog.messages.no_customers') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $customers->links() }}</div>
</div></section>
@endsection
