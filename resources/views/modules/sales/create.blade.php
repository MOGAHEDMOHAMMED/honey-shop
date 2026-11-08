@extends('layouts.app')

@section('title', __('catalog.pages.sale_create'))
@section('heading', __('catalog.pages.sale_create'))
@section('breadcrumb')
    <a href="{{ route('sales.index') }}" class="text-decoration-none">{{ __('catalog.pages.sales') }}</a>
    / {{ __('catalog.actions.create_sale') }}
@endsection

@section('content')
<section class="card page-card"><div class="card-body p-4">
    @include('modules.sales._form')
</div></section>
@endsection
