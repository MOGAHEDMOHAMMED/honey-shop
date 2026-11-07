@extends('layouts.app')

@section('title', __('catalog.pages.categories'))
@section('heading', __('catalog.pages.categories'))
@section('breadcrumb', __('catalog.navigation.catalog').' / '.__('catalog.pages.categories'))

@section('content')
<section class="card page-card">
    <div class="card-body">
        <form method="get" action="{{ route('categories.index') }}" class="row g-2 align-items-end mb-4">
            <div class="col-12 col-md-8 col-lg-6">
                <label for="category-search" class="form-label">{{ __('catalog.fields.search') }}</label>
                <input id="category-search" name="search" value="{{ $search }}"
                       class="form-control" maxlength="100">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('catalog.actions.search') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('categories.index') }}">
                    {{ __('catalog.actions.clear') }}
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('catalog.fields.name') }}</th>
                    <th>{{ __('catalog.fields.status') }}</th>
                    <th>{{ __('catalog.fields.product_count') }}</th>
                    @can('catalog.categories.manage')
                        <th>{{ __('catalog.fields.actions') }}</th>
                    @endcan
                </tr>
                </thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="fw-semibold">{{ __('catalog.categories.'.$category->name) }}</td>
                        <td>
                            <span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $category->is_active ? __('catalog.fields.active') : __('catalog.fields.inactive') }}
                            </span>
                        </td>
                        <td>{{ number_format($category->products_count) }}</td>
                        @can('catalog.categories.manage')
                            <td>
                                <form method="post" action="{{ route('categories.status', $category) }}"
                                      onsubmit="return confirm('{{ __('catalog.messages.confirm_category_status') }}')">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $category->is_active ? '0' : '1' }}">
                                    <button class="btn btn-sm {{ $category->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                            type="submit">
                                        {{ $category->is_active ? __('catalog.actions.deactivate') : __('catalog.actions.activate') }}
                                    </button>
                                </form>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-5">
                            {{ __('catalog.messages.no_categories') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $categories->links() }}</div>
    </div>
</section>
@endsection
