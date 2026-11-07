@extends('layouts.app')

@section('title', __('catalog.pages.purchase_edit'))
@section('heading', __('catalog.pages.purchase_edit'))
@section('breadcrumb')
    <a href="{{ route('purchases.index') }}" class="text-decoration-none">{{ __('catalog.pages.purchases') }}</a>
    / {{ $purchase->invoice_number }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        @include('modules.purchases._form')
    </div>
</section>
@endsection
