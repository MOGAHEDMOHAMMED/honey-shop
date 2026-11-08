@extends('layouts.app')

@section('title', $customer->name)
@section('heading', __('catalog.pages.customer_details'))
@section('breadcrumb')
    <a href="{{ route('customers.index') }}" class="text-decoration-none">{{ __('catalog.pages.customers') }}</a>
    / {{ $customer->name }}
@endsection

@section('content')
<section class="card page-card mb-4"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div><h2 class="h4 mb-2">{{ $customer->name }}</h2>
            <span class="badge {{ $customer->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                {{ $customer->is_active ? __('catalog.fields.active') : __('catalog.fields.inactive') }}
            </span>
        </div>
        @can('customers.manage')
            <a class="btn btn-outline-primary" href="{{ route('customers.edit', $customer) }}">{{ __('catalog.actions.edit') }}</a>
        @endcan
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.phone') }}</div><div dir="ltr">{{ $customer->phone ?: '—' }}</div></div>
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.country') }}</div><div>{{ $customer->country ?: '—' }}</div></div>
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.city') }}</div><div>{{ $customer->city ?: '—' }}</div></div>
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.customer_balance') }}</div>
            <div class="fw-bold" dir="ltr">{{ \App\Support\Money::format((string) $balance) }} {{ __('catalog.currency') }}</div></div>
        <div class="col-12"><div class="text-secondary small">{{ __('catalog.fields.address') }}</div><div>{{ $customer->address ?: '—' }}</div></div>
        <div class="col-12"><div class="text-secondary small">{{ __('catalog.fields.notes') }}</div><div>{{ $customer->notes ?: __('catalog.messages.no_description') }}</div></div>
    </div>
</div></section>

<section class="card page-card mb-4"><div class="card-body">
    <h2 class="h5 mb-3">{{ __('catalog.fields.customer_sales') }}</h2>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>{{ __('catalog.fields.invoice_number') }}</th><th>{{ __('catalog.fields.sales_channel') }}</th>
            <th>{{ __('catalog.fields.document_date') }}</th><th>{{ __('catalog.fields.invoice_total') }}</th><th>{{ __('catalog.fields.status') }}</th></tr></thead>
        <tbody>
        @forelse ($sales as $sale)
            <tr><td><a href="{{ route('sales.show', $sale) }}" class="text-decoration-none">{{ $sale->invoice_number }}</a></td>
                <td>{{ __('catalog.fields.'.$sale->sales_channel.'_sale') }}</td>
                <td>{{ $sale->businessTransaction->occurred_at->format('Y-m-d') }}</td>
                <td dir="ltr">{{ \App\Support\Money::format($sale->final_total) }} {{ __('catalog.currency') }}</td>
                <td>{{ __('catalog.statuses.'.$sale->businessTransaction->status) }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('catalog.messages.no_sales') }}</td></tr>
        @endforelse
        </tbody>
    </table></div><div class="mt-3">{{ $sales->links() }}</div>
</div></section>

<section class="card page-card"><div class="card-body">
    <h2 class="h5 mb-3">{{ __('catalog.pages.customer_ledger') }}</h2>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>{{ __('catalog.fields.document_date') }}</th><th>{{ __('catalog.fields.invoice_number') }}</th>
            <th>{{ __('catalog.fields.debit') }}</th><th>{{ __('catalog.fields.credit') }}</th><th>{{ __('catalog.fields.description') }}</th></tr></thead>
        <tbody>
        @forelse ($entries as $entry)
            <tr><td>{{ $entry->created_at->format('Y-m-d H:i') }}</td><td>{{ $entry->transaction_id }}</td>
                <td dir="ltr">{{ \App\Support\Money::format($entry->debit) }} {{ __('catalog.currency') }}</td>
                <td dir="ltr">{{ \App\Support\Money::format($entry->credit) }} {{ __('catalog.currency') }}</td><td>{{ $entry->description ?: '—' }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('catalog.messages.no_customer_ledger') }}</td></tr>
        @endforelse
        </tbody>
    </table></div><div class="mt-3">{{ $entries->links() }}</div>
</div></section>
@endsection
