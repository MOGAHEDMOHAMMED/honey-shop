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
                    {{ \App\Support\Money::format($product->selling_price) }} {{ __('catalog.currency') }}
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.max_discount_percentage') }}</div>
                <div class="fw-semibold">
                    {{ \App\Support\Money::format(bcmul((string) $product->max_discount_rate, '100', 4), 4) }}%
                </div>
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

@can('catalog.products.price-history.view')
    <section class="card page-card mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">{{ __('catalog.pages.product_price_history') }}</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('catalog.fields.changed_at') }}</th>
                        <th>{{ __('catalog.fields.previous_price') }}</th>
                        <th>{{ __('catalog.fields.new_price') }}</th>
                        <th>{{ __('catalog.fields.previous_discount') }}</th>
                        <th>{{ __('catalog.fields.new_discount') }}</th>
                        <th>{{ __('catalog.fields.changed_by') }}</th>
                        <th>{{ __('catalog.fields.change_reason') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($priceHistory as $history)
                        <tr>
                            <td>{{ $history->created_at->format('Y-m-d H:i') }}</td>
                            <td dir="ltr">
                                @if ($history->previous_selling_price !== null)
                                    {{ \App\Support\Money::format($history->previous_selling_price) }}
                                    {{ __('catalog.currency') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td dir="ltr">
                                {{ \App\Support\Money::format($history->new_selling_price) }}
                                {{ __('catalog.currency') }}
                            </td>
                            <td>
                                @if ($history->previous_max_discount_rate !== null)
                                    {{ \App\Support\Money::format(bcmul((string) $history->previous_max_discount_rate, '100', 4), 4) }}%
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                {{ \App\Support\Money::format(bcmul((string) $history->new_max_discount_rate, '100', 4), 4) }}%
                            </td>
                            <td>{{ $history->actor?->display_name ?: '—' }}</td>
                            <td>{{ $history->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                                {{ __('catalog.messages.no_price_history') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $priceHistory->links() }}</div>
        </div>
    </section>
@endcan
@endsection
