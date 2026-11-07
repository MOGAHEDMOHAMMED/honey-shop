@extends('layouts.app')

@section('title', __('catalog.pages.product_create'))
@section('heading', __('catalog.pages.product_create'))
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="text-decoration-none">{{ __('catalog.pages.products') }}</a>
    / {{ __('catalog.pages.product_create') }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        <form method="post" action="{{ route('products.store') }}">
            @csrf
            @include('modules.catalog.products._form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('products.index') }}">
                    {{ __('catalog.actions.cancel') }}
                </a>
            </div>
        </form>
    </div>
</section>
@endsection
