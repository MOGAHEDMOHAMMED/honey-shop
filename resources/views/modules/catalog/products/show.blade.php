@extends('layouts.app')

@section('title', $product->name)
@section('heading', __('catalog.pages.product_details'))
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="text-decoration-none">{{ __('catalog.pages.products') }}</a>
    / {{ $product->name }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h4 mb-1">{{ $product->name }}</h2>
                @if ($product->sku)
                    <div class="text-secondary">{{ __('catalog.fields.sku') }}: {{ $product->sku }}</div>
                @endif
            </div>
            @can('catalog.products.manage')
                <a class="btn btn-outline-primary" href="{{ route('products.edit', $product) }}">
                    {{ __('catalog.actions.edit') }}
                </a>
            @endcan
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.category') }}</div>
                <div class="fw-semibold">{{ __('catalog.categories.'.$product->category->name) }}</div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.base_unit') }}</div>
                <div class="fw-semibold">{{ __('catalog.units.'.$product->base_unit->value) }}</div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.selling_price') }}</div>
                <div class="fw-semibold" dir="ltr">
                    {{ number_format((float) $product->selling_price, 4) }} {{ __('catalog.currency') }}
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.max_discount_percentage') }}</div>
                <div class="fw-semibold">{{ number_format((float) $product->max_discount_rate * 100, 4) }}%</div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.minimum_stock') }}</div>
                <div class="fw-semibold">
                    {{ $product->minimum_stock ?? '—' }} {{ __('catalog.units.'.$product->base_unit->value) }}
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.stock') }}</div>
                <div class="fw-semibold">
                    {{ number_format((float) ($product->inventoryBalance?->quantity ?? 0), 3) }}
                    {{ __('catalog.units.'.$product->base_unit->value) }}
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.status') }}</div>
                <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                    {{ $product->is_active ? __('catalog.fields.active') : __('catalog.fields.inactive') }}
                </span>
            </div>
            <div class="col-12">
                <div class="text-secondary small">{{ __('catalog.fields.notes') }}</div>
                <div>{{ $product->notes ?: __('catalog.messages.no_description') }}</div>
            </div>
        </div>
    </div>
</section>
@endsection
