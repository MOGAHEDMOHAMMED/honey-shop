@extends('layouts.app')

@section('title', __('catalog.pages.opening_inventory'))
@section('heading', __('catalog.pages.opening_inventory'))
@section('breadcrumb')
    <a href="{{ route('opening-inventory.index') }}" class="text-decoration-none">
        {{ __('catalog.pages.opening_inventory') }}
    </a>
    / {{ $openingInventory->transaction_id }}
@endsection

@section('content')
<section class="card page-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h5 mb-2">{{ $openingInventory->transaction_id }}</h2>
                <span class="badge {{ $openingInventory->businessTransaction->status === 'approved' ? 'text-bg-success' : 'text-bg-warning' }}">
                    {{ __('catalog.statuses.'.$openingInventory->businessTransaction->status) }}
                </span>
            </div>
            <div class="d-flex gap-2">
                @if ($openingInventory->businessTransaction->status === 'draft')
                    @can('opening-inventory.manage')
                        <a class="btn btn-outline-primary" href="{{ route('opening-inventory.edit', $openingInventory) }}">
                            {{ __('catalog.actions.edit') }}
                        </a>
                        <form method="post" action="{{ route('opening-inventory.approve', $openingInventory) }}"
                              onsubmit="return confirm('{{ __('catalog.messages.confirm_approve') }}')">
                            @csrf
                            <button class="btn btn-primary" type="submit">{{ __('catalog.actions.approve') }}</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.effective_at') }}</div>
                <div class="fw-semibold">{{ $openingInventory->effective_at->format('Y-m-d') }}</div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-secondary small">{{ __('catalog.fields.created_by') }}</div>
                <div class="fw-semibold">{{ $openingInventory->businessTransaction->creator?->display_name ?: '—' }}</div>
            </div>
            @if ($openingInventory->businessTransaction->approved_at)
                <div class="col-12 col-md-6">
                    <div class="text-secondary small">{{ __('catalog.fields.approved_by') }}</div>
                    <div class="fw-semibold">{{ $openingInventory->businessTransaction->approver?->display_name ?: '—' }}</div>
                </div>
            @endif
            <div class="col-12">
                <div class="text-secondary small">{{ __('catalog.fields.notes') }}</div>
                <div>{{ $openingInventory->businessTransaction->notes ?: __('catalog.messages.no_description') }}</div>
            </div>
        </div>
    </div>
</section>

<section class="card page-card">
    <div class="card-body">
        <div class="alert alert-info">{{ __('catalog.messages.opening_inventory_costing_note') }}</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.product') }}</th>
                    <th>{{ __('catalog.fields.entered_quantity') }}</th>
                    <th>{{ __('catalog.fields.input_unit') }}</th>
                    <th>{{ __('catalog.fields.conversion_factor') }}</th>
                    <th>{{ __('catalog.fields.base_quantity') }}</th>
                    <th>{{ __('catalog.fields.stock') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($openingInventory->lines as $line)
                    <tr>
                        <td>
                            <a class="text-decoration-none"
                               href="{{ route('products.show', $line->product) }}">{{ $line->product->name }}</a>
                        </td>
                        <td>{{ $line->entered_quantity }}</td>
                        <td>{{ __('catalog.units.'.$line->input_unit) }}</td>
                        <td>{{ $line->conversion_factor }}</td>
                        <td>{{ $line->quantity }} {{ __('catalog.units.'.$line->product->base_unit->value) }}</td>
                        <td>{{ \App\Support\Money::format((string) ($stock[$line->product_id] ?? '0'), 3) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
