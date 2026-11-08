@extends('layouts.app')

@section('title', __('catalog.pages.customer_create'))
@section('heading', __('catalog.pages.customer_create'))
@section('breadcrumb')
    <a href="{{ route('customers.index') }}" class="text-decoration-none">{{ __('catalog.pages.customers') }}</a>
    / {{ __('catalog.actions.create_customer') }}
@endsection

@section('content')
<section class="card page-card"><div class="card-body p-4">
    @include('modules.customers._form')
</div></section>
@endsection
