@extends('layouts.app')

@section('title', __('catalog.pages.customer_edit'))
@section('heading', __('catalog.pages.customer_edit'))
@section('breadcrumb')
    <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none">{{ $customer->name }}</a>
    / {{ __('catalog.actions.edit') }}
@endsection

@section('content')
<section class="card page-card"><div class="card-body p-4">
    @include('modules.customers._form')
</div></section>
@endsection
