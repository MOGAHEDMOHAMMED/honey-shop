@extends('layouts.app')

@section('title', __('catalog.pages.supplier_edit'))
@section('heading', __('catalog.pages.supplier_edit'))
@section('breadcrumb')
    <a href="{{ route('suppliers.index') }}" class="text-decoration-none">{{ __('catalog.pages.suppliers') }}</a>
    / {{ $supplier->name }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        <form method="post" action="{{ route('suppliers.update', $supplier) }}">
            @csrf
            @method('PUT')
            @include('modules.suppliers._form')
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('suppliers.show', $supplier) }}">
                    {{ __('catalog.actions.cancel') }}
                </a>
            </div>
        </form>
    </div>
</section>
@endsection
