@extends('layouts.app')

@section('title', $supplier->name)
@section('heading', __('catalog.pages.supplier_details'))
@section('breadcrumb')
    <a href="{{ route('suppliers.index') }}" class="text-decoration-none">{{ __('catalog.pages.suppliers') }}</a>
    / {{ $supplier->name }}
@endsection

@section('content')
<section class="card page-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h4 mb-2">{{ $supplier->name }}</h2>
                <span class="badge {{ $supplier->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                    {{ $supplier->is_active ? __('catalog.fields.active') : __('catalog.fields.inactive') }}
                </span>
            </div>
            @can('suppliers.manage')
                <a class="btn btn-outline-primary" href="{{ route('suppliers.edit', $supplier) }}">
                    {{ __('catalog.actions.edit') }}
                </a>
            @endcan
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.supplier_type') }}</div>
                <div class="fw-semibold">
                    {{ __('catalog.supplier_types.'.$supplier->type->name, ['name' => $supplier->type->name]) }}
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.phone') }}</div>
                <div class="fw-semibold" dir="ltr">{{ $supplier->phone ?: '—' }}</div>
            </div>
            <div class="col-12">
                <div class="text-secondary small">{{ __('catalog.fields.address') }}</div>
                <div>{{ $supplier->address ?: '—' }}</div>
            </div>
            <div class="col-12">
                <div class="text-secondary small">{{ __('catalog.fields.notes') }}</div>
                <div>{{ $supplier->notes ?: __('catalog.messages.no_description') }}</div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.supplier_balance') }}</div>
                <div class="fw-semibold" dir="ltr">
                    {{ \App\Support\Money::format((string) $balance) }} {{ __('catalog.currency') }}
                </div>
            </div>
        </div>
    </div>
</section>

<section class="card page-card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">{{ __('catalog.pages.purchases') }}</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.purchase_reference') }}</th>
                    <th>{{ __('catalog.fields.supplier_invoice_number') }}</th>
                    <th>{{ __('catalog.fields.document_date') }}</th>
                    <th>{{ __('catalog.fields.invoice_total') }}</th>
                    <th>{{ __('catalog.fields.status') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>
                            <a class="text-decoration-none"
                               href="{{ route('purchases.show', $purchase->transaction_id) }}">
                                {{ $purchase->invoice_number }}
                            </a>
                        </td>
                        <td>{{ $purchase->supplier_invoice_number ?: '—' }}</td>
                        <td>{{ $purchase->businessTransaction->occurred_at->format('Y-m-d') }}</td>
                        <td dir="ltr">
                            {{ \App\Support\Money::format($purchase->invoice_total) }} {{ __('catalog.currency') }}
                        </td>
                        <td>{{ __('catalog.statuses.'.$purchase->businessTransaction->status) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-secondary py-4">
                            {{ __('catalog.messages.no_purchases') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $purchases->links() }}</div>
    </div>
</section>

<section class="card page-card">
    <div class="card-body">
        <h2 class="h5 mb-3">{{ __('catalog.pages.supplier_ledger') }}</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.document_date') }}</th>
                    <th>{{ __('catalog.fields.purchase_reference') }}</th>
                    <th>{{ __('catalog.fields.description') }}</th>
                    <th>{{ __('catalog.fields.debit') }}</th>
                    <th>{{ __('catalog.fields.credit') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $entry->transaction_id }}</td>
                        <td>{{ $entry->description ?: '—' }}</td>
                        <td dir="ltr">{{ \App\Support\Money::format($entry->debit) }} {{ __('catalog.currency') }}</td>
                        <td dir="ltr">{{ \App\Support\Money::format($entry->credit) }} {{ __('catalog.currency') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-secondary py-4">
                            {{ __('catalog.messages.no_ledger_entries') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $entries->links() }}</div>
    </div>
</section>
@endsection
