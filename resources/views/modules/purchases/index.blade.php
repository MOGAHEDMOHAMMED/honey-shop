@extends('layouts.app')

@section('title', __('catalog.pages.purchases'))
@section('heading', __('catalog.pages.purchases'))
@section('breadcrumb', __('catalog.navigation.procurement').' / '.__('catalog.pages.purchases'))

@section('content')
<section class="card page-card">
    <div class="card-body">
        <form method="get" action="{{ route('purchases.index') }}" class="row g-2 align-items-end mb-4">
            <div class="col-12 col-md-6 col-lg-5">
                <label for="purchase-search" class="form-label">{{ __('catalog.fields.search') }}</label>
                <input id="purchase-search" name="search" value="{{ $search }}"
                       class="form-control" maxlength="100">
            </div>
            <div class="col-12 col-md-3">
                <label for="purchase-status" class="form-label">{{ __('catalog.fields.status') }}</label>
                <select id="purchase-status" name="status" class="form-select">
                    <option value="">{{ __('catalog.fields.all_statuses') }}</option>
                    @foreach (['draft', 'approved'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                            {{ __('catalog.statuses.'.$status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.search') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('purchases.index') }}">
                    {{ __('catalog.actions.clear') }}
                </a>
            </div>
            @can('purchases.manage')
                <div class="col-12 col-lg-auto me-lg-auto">
                    <a class="btn btn-success" href="{{ route('purchases.create') }}">
                        {{ __('catalog.actions.create_purchase') }}
                    </a>
                </div>
            @endcan
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.purchase_reference') }}</th>
                    <th>{{ __('catalog.fields.supplier_invoice_number') }}</th>
                    <th>{{ __('catalog.fields.supplier') }}</th>
                    <th>{{ __('catalog.fields.document_date') }}</th>
                    <th>{{ __('catalog.fields.invoice_total') }}</th>
                    <th>{{ __('catalog.fields.status') }}</th>
                    <th>{{ __('catalog.fields.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>{{ $purchase->invoice_number }}</td>
                        <td>{{ $purchase->supplier_invoice_number }}</td>
                        <td>
                            <a class="text-decoration-none"
                               href="{{ route('suppliers.show', $purchase->supplier) }}">
                                {{ $purchase->supplier->name }}
                            </a>
                        </td>
                        <td>{{ $purchase->businessTransaction->occurred_at->format('Y-m-d') }}</td>
                        <td dir="ltr">
                            {{ \App\Support\Money::format($purchase->invoice_total) }} {{ __('catalog.currency') }}
                        </td>
                        <td>{{ __('catalog.statuses.'.$purchase->businessTransaction->status) }}</td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('purchases.show', $purchase) }}">{{ __('catalog.actions.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
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
@endsection
