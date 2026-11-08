@extends('layouts.app')

@section('title', $purchase->invoice_number)
@section('heading', __('catalog.pages.purchase_details'))
@section('breadcrumb')
    <a href="{{ route('purchases.index') }}" class="text-decoration-none">{{ __('catalog.pages.purchases') }}</a>
    / {{ $purchase->invoice_number }}
@endsection

@section('content')
<section class="card page-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h4 mb-2">{{ __('catalog.fields.purchase_invoice_number') }}: {{ $purchase->invoice_number }}</h2>
                <span class="badge {{ $purchase->businessTransaction->status === 'approved' ? 'text-bg-success' : 'text-bg-warning' }}">
                    {{ __('catalog.statuses.'.$purchase->businessTransaction->status) }}
                </span>
            </div>
            <div class="d-flex gap-2">
                @if ($purchase->businessTransaction->status === 'draft')
                    @can('purchases.manage')
                        <a class="btn btn-outline-primary" href="{{ route('purchases.edit', $purchase) }}">
                            {{ __('catalog.actions.edit') }}
                        </a>
                    @endcan
                    @can('purchases.approve')
                        <form method="post" action="{{ route('purchases.approve', $purchase) }}"
                              onsubmit="return confirm('{{ __('catalog.messages.confirm_approve') }}')">
                            @csrf
                            <button class="btn btn-primary" type="submit">{{ __('catalog.actions.approve') }}</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.supplier') }}</div>
                <a class="fw-semibold text-decoration-none"
                   href="{{ route('suppliers.show', $purchase->supplier) }}">{{ $purchase->supplier->name }}</a>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.supplier_invoice_number') }}</div>
                <div class="fw-semibold">{{ $purchase->supplier_invoice_number ?: '—' }}</div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.document_date') }}</div>
                <div class="fw-semibold">{{ $purchase->businessTransaction->occurred_at->format('Y-m-d') }}</div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.created_by') }}</div>
                <div class="fw-semibold">{{ $purchase->businessTransaction->creator?->display_name ?: '—' }}</div>
            </div>
            @if ($purchase->businessTransaction->approved_at)
                <div class="col-12 col-md-6">
                    <div class="text-secondary small">{{ __('catalog.fields.approved_by') }}</div>
                    <div class="fw-semibold">{{ $purchase->businessTransaction->approver?->display_name ?: '—' }}</div>
                </div>
            @endif
            <div class="col-12">
                <div class="text-secondary small">{{ __('catalog.fields.notes') }}</div>
                <div>{{ $purchase->businessTransaction->notes ?: __('catalog.messages.no_description') }}</div>
            </div>
        </div>
    </div>
</section>

<section class="card page-card">
    <div class="card-body">
        <h2 class="h5 mb-3">{{ __('catalog.fields.purchase_lines') }}</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.product') }}</th>
                    <th>{{ __('catalog.fields.entered_quantity') }}</th>
                    <th>{{ __('catalog.fields.input_unit') }}</th>
                    <th>{{ __('catalog.fields.conversion_factor') }}</th>
                    <th>{{ __('catalog.fields.base_quantity') }}</th>
                    <th>{{ __('catalog.fields.unit_cost') }}</th>
                    <th>{{ __('catalog.fields.base_unit_cost') }}</th>
                    <th>{{ __('catalog.fields.line_total') }}</th>
                    <th>{{ __('catalog.fields.stock') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($purchase->lines as $line)
                    <tr>
                        <td>
                            <a class="text-decoration-none"
                               href="{{ route('products.show', $line->product) }}">{{ $line->product->name }}</a>
                        </td>
                        <td>{{ $line->entered_quantity }}</td>
                        <td>{{ __('catalog.units.'.$line->input_unit) }}</td>
                        <td>{{ $line->conversion_factor }}</td>
                        <td>{{ $line->base_quantity }} {{ __('catalog.units.'.$line->product->base_unit->value) }}</td>
                        <td dir="ltr">
                            {{ \App\Support\Money::format($line->purchase_unit_price) }} {{ __('catalog.currency') }}
                        </td>
                        <td dir="ltr">
                            {{ \App\Support\Money::format($line->effective_base_unit_cost) }} {{ __('catalog.currency') }}
                        </td>
                        <td dir="ltr">
                            {{ \App\Support\Money::format($line->line_total) }} {{ __('catalog.currency') }}
                        </td>
                        <td>{{ \App\Support\Money::format((string) ($stock[$line->product_id] ?? '0'), 3) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="7">{{ __('catalog.fields.invoice_total') }}</th>
                    <th colspan="2" dir="ltr">
                        {{ \App\Support\Money::format($purchase->invoice_total) }} {{ __('catalog.currency') }}
                    </th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>
@endsection
