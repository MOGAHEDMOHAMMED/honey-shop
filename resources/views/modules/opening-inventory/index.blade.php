@extends('layouts.app')

@section('title', __('catalog.pages.opening_inventory'))
@section('heading', __('catalog.pages.opening_inventory'))
@section('breadcrumb', __('catalog.navigation.procurement').' / '.__('catalog.pages.opening_inventory'))

@section('content')
<section class="card page-card">
    <div class="card-body">
        <form method="get" action="{{ route('opening-inventory.index') }}" class="row g-2 align-items-end mb-4">
            <div class="col-12 col-md-4">
                <label for="opening-status" class="form-label">{{ __('catalog.fields.status') }}</label>
                <select id="opening-status" name="status" class="form-select">
                    <option value="">{{ __('catalog.fields.all_statuses') }}</option>
                    @foreach (['draft', 'approved'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                            {{ __('catalog.statuses.'.$status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.search') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('opening-inventory.index') }}">
                    {{ __('catalog.actions.clear') }}
                </a>
            </div>
            @can('opening-inventory.manage')
                <div class="col-12 col-lg-auto me-lg-auto">
                    <a class="btn btn-success" href="{{ route('opening-inventory.create') }}">
                        {{ __('catalog.actions.create_opening_inventory') }}
                    </a>
                </div>
            @endcan
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.purchase_reference') }}</th>
                    <th>{{ __('catalog.fields.effective_at') }}</th>
                    <th>{{ __('catalog.fields.line_count') }}</th>
                    <th>{{ __('catalog.fields.status') }}</th>
                    <th>{{ __('catalog.fields.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td>{{ $document->transaction_id }}</td>
                        <td>{{ $document->effective_at->format('Y-m-d') }}</td>
                        <td>{{ number_format($document->lines_count) }}</td>
                        <td>{{ __('catalog.statuses.'.$document->businessTransaction->status) }}</td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('opening-inventory.show', $document) }}">{{ __('catalog.actions.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-secondary py-5">
                            {{ __('catalog.messages.no_opening_inventory') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $documents->links() }}</div>
    </div>
</section>
@endsection
