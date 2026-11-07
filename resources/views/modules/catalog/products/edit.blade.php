@extends('layouts.app')

@section('title', __('catalog.pages.product_edit'))
@section('heading', __('catalog.pages.product_edit'))
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="text-decoration-none">{{ __('catalog.pages.products') }}</a>
    / <a href="{{ route('products.show', $product) }}" class="text-decoration-none">{{ $product->name }}</a>
    / {{ __('catalog.actions.edit') }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        <form method="post" action="{{ route('products.update', $product) }}">
            @csrf
            @method('PUT')
            @include('modules.catalog.products._form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('products.show', $product) }}">
                    {{ __('catalog.actions.cancel') }}
                </a>
            </div>
        </form>
    </div>
</section>
@endsection
