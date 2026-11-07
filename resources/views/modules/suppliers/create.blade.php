@extends('layouts.app')

@section('title', __('catalog.pages.supplier_create'))
@section('heading', __('catalog.pages.supplier_create'))
@section('breadcrumb')
    <a href="{{ route('suppliers.index') }}" class="text-decoration-none">{{ __('catalog.pages.suppliers') }}</a>
    / {{ __('catalog.pages.supplier_create') }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        <form method="post" action="{{ route('suppliers.store') }}">
            @csrf
            @include('modules.suppliers._form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('suppliers.index') }}">
                    {{ __('catalog.actions.cancel') }}
                </a>
            </div>
        </form>
    </div>
</section>
@endsection
