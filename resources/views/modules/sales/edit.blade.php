@extends('layouts.app')

@section('title', __('catalog.pages.sale_edit'))
@section('heading', __('catalog.pages.sale_edit'))
@section('breadcrumb')
    <a href="{{ route('sales.show', $sale) }}" class="text-decoration-none">{{ $sale->invoice_number }}</a>
    / {{ __('catalog.actions.edit') }}
@endsection

@section('content')
<section class="card page-card"><div class="card-body p-4">
    @include('modules.sales._form')
</div></section>
@endsection
