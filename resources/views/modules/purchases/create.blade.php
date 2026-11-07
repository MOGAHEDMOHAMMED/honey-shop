@extends('layouts.app')

@section('title', __('catalog.pages.purchase_create'))
@section('heading', __('catalog.pages.purchase_create'))
@section('breadcrumb')
    <a href="{{ route('purchases.index') }}" class="text-decoration-none">{{ __('catalog.pages.purchases') }}</a>
    / {{ __('catalog.pages.purchase_create') }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        @include('modules.purchases._form', ['purchase' => null])
    </div>
</section>
@endsection
