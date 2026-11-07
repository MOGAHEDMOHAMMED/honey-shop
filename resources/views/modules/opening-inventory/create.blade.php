@extends('layouts.app')

@section('title', __('catalog.pages.opening_inventory_create'))
@section('heading', __('catalog.pages.opening_inventory_create'))
@section('breadcrumb')
    <a href="{{ route('opening-inventory.index') }}" class="text-decoration-none">
        {{ __('catalog.pages.opening_inventory') }}
    </a>
    / {{ __('catalog.pages.opening_inventory_create') }}
@endsection

@section('content')
@if ($alreadyApproved)
    <div class="alert alert-info" role="status">
        {{ __('catalog.errors.opening_inventory_already_approved') }}
        <a class="alert-link" href="{{ route('opening-inventory.show', $approvedDocument) }}">
            {{ __('catalog.actions.view') }}
        </a>
    </div>
@else
    <section class="card page-card">
        <div class="card-body p-4">
            <p class="text-secondary">{{ __('catalog.messages.opening_inventory_costing_note') }}</p>
            @include('modules.opening-inventory._form', ['openingInventory' => null])
        </div>
    </section>
@endif
@endsection
