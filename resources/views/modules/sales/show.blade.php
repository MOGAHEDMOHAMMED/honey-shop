@extends('layouts.app')

@section('title', $sale->invoice_number)
@section('heading', __('catalog.pages.sale_details'))
@section('breadcrumb')
    <a href="{{ route('sales.index') }}" class="text-decoration-none">{{ __('catalog.pages.sales') }}</a>
    / {{ $sale->invoice_number }}
@endsection

@section('content')
@php
    $categories = $sale->lines->map(fn ($line) => mb_strtolower($line->product->category->name))->unique()->values();
    $isMixed = $categories->count() > 1;
    $seriesLabels = [
        'honey' => __('catalog.fields.honey_series'),
        'ghee' => __('catalog.fields.ghee_series'),
        'nuts' => __('catalog.fields.nuts_series'),
    ];
@endphp
<section class="card page-card mb-4"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="text-secondary small">{{ __('catalog.fields.invoice_number') }}</div>
            <h2 class="h4 mb-2">{{ $sale->invoice_number }}</h2>
            <span class="badge {{ $sale->businessTransaction->status === 'approved' ? 'text-bg-success' : 'text-bg-warning' }}">
                {{ __('catalog.statuses.'.$sale->businessTransaction->status) }}
            </span>
            @if ($sale->sales_channel === 'external')
                <span class="badge text-bg-info">{{ __('catalog.order_statuses.'.$sale->order_status) }}</span>
            @endif
        </div>
        @if ($sale->businessTransaction->status === 'draft')
            <div class="d-flex flex-wrap gap-2">
                @can('sales.manage')
                    <a class="btn btn-outline-primary" href="{{ route('sales.edit', $sale) }}">{{ __('catalog.actions.edit') }}</a>
                @endcan
                @can('sales.approve')
                    <form method="post" action="{{ route('sales.approve', $sale) }}"
                          onsubmit="return confirm('{{ __('catalog.messages.confirm_sale_approve') }}')">
                        @csrf
                        @if ($isMixed)
                            <label for="series-category" class="form-label">{{ __('catalog.fields.series_category') }}</label>
                            <select id="series-category" name="series_category" class="form-select mb-2" required>
                                <option value="">{{ __('catalog.fields.choose_series') }}</option>
                                @foreach ($seriesLabels as $category => $label)
                                    <option value="{{ $category }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text mb-2">{{ __('catalog.messages.mixed_series_note') }}</div>
                        @endif
                        <button class="btn btn-primary" type="submit">{{ __('catalog.actions.approve_sale') }}</button>
                    </form>
                @endcan
            </div>
        @endif
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.customer') }}</div>
            <a class="fw-semibold text-decoration-none" href="{{ route('customers.show', $sale->customer) }}">{{ $sale->customer->name }}</a></div>
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.sales_channel') }}</div>
            <div class="fw-semibold">{{ __('catalog.fields.'.$sale->sales_channel.'_sale') }}</div></div>
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.document_date') }}</div>
            <div class="fw-semibold">{{ $sale->businessTransaction->occurred_at->format('Y-m-d') }}</div></div>
        <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.series_category') }}</div>
            <div class="fw-semibold">{{ $sale->series_category ? $seriesLabels[$sale->series_category] : '—' }}</div></div>
        @if ($sale->sales_channel === 'external')
            <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.delivery_charge') }}</div>
                <div dir="ltr">{{ \App\Support\Money::format($sale->delivery_charge) }} {{ __('catalog.currency') }}</div></div>
            <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.delivery_cost_bearer') }}</div>
                <div>{{ __('catalog.fields.delivery_'.$sale->delivery_cost_bearer) }}</div></div>
            <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.delivery_provider') }}</div>
                <div>{{ $deliveryProvider ?: '—' }}</div></div>
            <div class="col-12 col-md-6"><div class="text-secondary small">{{ __('catalog.fields.representative') }}</div>
                <div>{{ $representative ?: '—' }}</div></div>
        @endif
        <div class="col-12"><div class="text-secondary small">{{ __('catalog.fields.notes') }}</div>
            <div>{{ $sale->businessTransaction->notes ?: __('catalog.messages.no_description') }}</div></div>
    </div>
</div></section>

<section class="card page-card"><div class="card-body">
    <h2 class="h5 mb-3">{{ __('catalog.fields.sale_lines') }}</h2>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr>
            <th>{{ __('catalog.fields.product') }}</th><th>{{ __('catalog.fields.entered_quantity') }}</th>
            <th>{{ __('catalog.fields.input_unit') }}</th><th>{{ __('catalog.fields.base_quantity') }}</th>
            <th>{{ __('catalog.fields.unit_price') }}</th><th>{{ __('catalog.fields.discount_percentage') }}</th>
            <th>{{ __('catalog.fields.discount_amount') }}</th><th>{{ __('catalog.fields.line_total') }}</th>
            <th>{{ __('catalog.fields.stock') }}</th>
        </tr></thead>
        <tbody>
        @foreach ($sale->lines as $line)
            <tr>
                <td><a href="{{ route('products.show', $line->product) }}" class="text-decoration-none">{{ $line->product->name }}</a></td>
                <td>{{ $line->entered_quantity }}</td>
                <td>{{ __('catalog.units.'.$line->input_unit) }}</td>
                <td>{{ $line->base_quantity }} {{ __('catalog.units.'.$line->product->base_unit->value) }}</td>
                <td dir="ltr">{{ \App\Support\Money::format($line->unit_price) }} {{ __('catalog.currency') }}</td>
                <td>{{ \App\Support\Money::format(bcmul((string) $line->discount_rate, '100', 4), 4) }}%</td>
                <td dir="ltr">{{ \App\Support\Money::format($line->discount_amount) }} {{ __('catalog.currency') }}</td>
                <td dir="ltr">{{ \App\Support\Money::format($line->line_total) }} {{ __('catalog.currency') }}</td>
                <td>{{ \App\Support\Money::format((string) ($stock[$line->product_id] ?? '0'), 3) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="7">{{ __('catalog.fields.products_subtotal') }}</th><th colspan="2" dir="ltr">{{ \App\Support\Money::format($sale->products_subtotal) }} {{ __('catalog.currency') }}</th></tr>
            <tr><th colspan="7">{{ __('catalog.fields.discounts_total') }}</th><th colspan="2" dir="ltr">−{{ \App\Support\Money::format($sale->discounts_total) }} {{ __('catalog.currency') }}</th></tr>
            @if ($sale->sales_channel === 'external')
                <tr><th colspan="7">{{ __('catalog.fields.delivery_charge') }}</th><th colspan="2" dir="ltr">{{ \App\Support\Money::format($sale->delivery_charge) }} {{ __('catalog.currency') }}</th></tr>
            @endif
            <tr class="table-light"><th colspan="7">{{ __('catalog.fields.invoice_total') }}</th>
                <th colspan="2" dir="ltr">{{ \App\Support\Money::format($sale->final_total) }} {{ __('catalog.currency') }}</th></tr>
        </tfoot>
    </table></div>
</div></section>
@endsection
