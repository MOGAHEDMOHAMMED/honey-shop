@extends('layouts.app')

@section('title', __('catalog.pages.opening_inventory_edit'))
@section('heading', __('catalog.pages.opening_inventory_edit'))
@section('breadcrumb')
    <a href="{{ route('opening-inventory.index') }}" class="text-decoration-none">
        {{ __('catalog.pages.opening_inventory') }}
    </a>
    / {{ $openingInventory->transaction_id }}
@endsection

@section('content')
<section class="card page-card">
    <div class="card-body p-4">
        @include('modules.opening-inventory._form')
    </div>
</section>
@endsection
