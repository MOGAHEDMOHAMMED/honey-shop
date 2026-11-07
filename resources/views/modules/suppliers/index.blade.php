@extends('layouts.app')

@section('title', __('catalog.pages.suppliers'))
@section('heading', __('catalog.pages.suppliers'))
@section('breadcrumb', __('catalog.navigation.procurement').' / '.__('catalog.pages.suppliers'))

@section('content')
<section class="card page-card">
    <div class="card-body">
        <form method="get" action="{{ route('suppliers.index') }}" class="row g-2 align-items-end mb-4">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="supplier-search" class="form-label">{{ __('catalog.fields.search') }}</label>
                <input id="supplier-search" name="search" value="{{ $search }}"
                       class="form-control" maxlength="100">
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="supplier-type" class="form-label">{{ __('catalog.fields.supplier_type') }}</label>
                <select id="supplier-type" name="type" class="form-select">
                    <option value="">{{ __('catalog.fields.all_types') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(($filters['type'] ?? '') === $type->id)>
                            {{ __('catalog.supplier_types.'.$type->name, ['name' => $type->name]) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="supplier-status" class="form-label">{{ __('catalog.fields.status') }}</label>
                <select id="supplier-status" name="status" class="form-select">
                    <option value="">{{ __('catalog.fields.all_statuses') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>
                        {{ __('catalog.fields.active') }}
                    </option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>
                        {{ __('catalog.fields.inactive') }}
                    </option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.search') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('suppliers.index') }}">
                    {{ __('catalog.actions.clear') }}
                </a>
            </div>
            @can('suppliers.manage')
                <div class="col-12 col-lg-auto me-lg-auto">
                    <a class="btn btn-success" href="{{ route('suppliers.create') }}">
                        {{ __('catalog.actions.create_supplier') }}
                    </a>
                </div>
            @endcan
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.name') }}</th>
                    <th>{{ __('catalog.fields.supplier_type') }}</th>
                    <th>{{ __('catalog.fields.phone') }}</th>
                    <th>{{ __('catalog.pages.purchases') }}</th>
                    <th>{{ __('catalog.fields.status') }}</th>
                    <th>{{ __('catalog.fields.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="{{ route('suppliers.show', $supplier) }}">
                                {{ $supplier->name }}
                            </a>
                        </td>
                        <td>{{ __('catalog.supplier_types.'.$supplier->type->name, ['name' => $supplier->type->name]) }}</td>
                        <td dir="ltr">{{ $supplier->phone ?: '—' }}</td>
                        <td>{{ number_format($supplier->purchases_count) }}</td>
                        <td>
                            <span class="badge {{ $supplier->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $supplier->is_active ? __('catalog.fields.active') : __('catalog.fields.inactive') }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('suppliers.show', $supplier) }}">{{ __('catalog.actions.view') }}</a>
                                @can('suppliers.manage')
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="{{ route('suppliers.edit', $supplier) }}">{{ __('catalog.actions.edit') }}</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-5">
                            {{ __('catalog.messages.no_suppliers') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $suppliers->links() }}</div>
    </div>
</section>
@endsection
