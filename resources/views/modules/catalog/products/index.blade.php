@extends('layouts.app')

@section('title', __('catalog.pages.products'))
@section('heading', __('catalog.pages.products'))
@section('breadcrumb', __('catalog.navigation.catalog').' / '.__('catalog.pages.products'))

@section('content')
<section class="card page-card">
    <div class="card-body">
        <form method="get" action="{{ route('products.index') }}" class="row g-2 align-items-end mb-4">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="product-search" class="form-label">{{ __('catalog.fields.search') }}</label>
                <input id="product-search" name="search" value="{{ $search }}"
                       class="form-control" maxlength="100">
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="category-filter" class="form-label">{{ __('catalog.fields.category') }}</label>
                <select id="category-filter" name="category" class="form-select">
                    <option value="">{{ __('catalog.fields.all_categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryId === $category->id)>
                            {{ __('catalog.categories.'.$category->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.search') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('products.index') }}">
                    {{ __('catalog.actions.clear') }}
                </a>
            </div>
            @can('catalog.products.manage')
                <div class="col-12 col-lg-auto me-lg-auto">
                    <a class="btn btn-success" href="{{ route('products.create') }}">
                        {{ __('catalog.actions.create') }}
                    </a>
                </div>
            @endcan
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.name') }}</th>
                    <th>{{ __('catalog.fields.category') }}</th>
                    <th>{{ __('catalog.fields.base_unit') }}</th>
                    <th>{{ __('catalog.fields.selling_price') }}</th>
                    <th>{{ __('catalog.fields.max_discount_percentage') }}</th>
                    <th>{{ __('catalog.fields.stock') }}</th>
                    <th>{{ __('catalog.fields.status') }}</th>
                    <th>{{ __('catalog.fields.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="{{ route('products.show', $product) }}">
                                {{ $product->name }}
                            </a>
                            @if ($product->sku)
                                <div class="text-secondary small">{{ $product->sku }}</div>
                            @endif
                        </td>
                        <td>{{ __('catalog.categories.'.$product->category->name) }}</td>
                        <td>{{ __('catalog.units.'.$product->base_unit->value) }}</td>
                        <td dir="ltr" class="text-end">
                            {{ number_format((float) $product->selling_price, 4) }} {{ __('catalog.currency') }}
                        </td>
                        <td>{{ number_format((float) $product->max_discount_rate * 100, 4) }}%</td>
                        <td>
                            {{ number_format((float) ($product->inventoryBalance?->quantity ?? 0), 3) }}
                            {{ __('catalog.units.'.$product->base_unit->value) }}
                        </td>
                        <td>
                            <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $product->is_active ? __('catalog.fields.active') : __('catalog.fields.inactive') }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('products.show', $product) }}">{{ __('catalog.actions.view') }}</a>
                                @can('catalog.products.manage')
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="{{ route('products.edit', $product) }}">{{ __('catalog.actions.edit') }}</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5">
                            {{ __('catalog.messages.no_products') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $products->links() }}</div>
    </div>
</section>
@endsection
