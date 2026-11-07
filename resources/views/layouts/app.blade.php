<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('catalog.app_name')) - {{ __('catalog.app_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-12 col-lg-3 col-xl-2 app-sidebar p-3 d-flex flex-column">
            <a class="app-brand d-flex align-items-center mb-4"
               href="{{ route('home') }}">
                <span class="app-brand-mark">ع</span>
                <span>{{ __('catalog.app_name') }}</span>
            </a>
            <div class="small text-white-50 mb-2">{{ __('catalog.navigation.catalog') }}</div>
            <nav class="sidebar-nav mb-4">
                @can('catalog.products.view')
                    <a class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}"
                       href="{{ route('products.index') }}">{{ __('catalog.navigation.products') }}</a>
                @endcan
                @can('catalog.categories.view')
                    <a class="sidebar-link {{ request()->routeIs('categories.*') ? 'active' : '' }}"
                       href="{{ route('categories.index') }}">{{ __('catalog.navigation.categories') }}</a>
                @endcan
            </nav>
            @if (auth()->user()->can('suppliers.view')
                || auth()->user()->can('opening-inventory.view')
                || auth()->user()->can('purchases.view'))
                <div class="small text-white-50 mb-2">{{ __('catalog.navigation.procurement') }}</div>
                <nav class="sidebar-nav mb-4">
                    @can('suppliers.view')
                        <a class="sidebar-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                           href="{{ route('suppliers.index') }}">{{ __('catalog.navigation.suppliers') }}</a>
                    @endcan
                    @can('opening-inventory.view')
                        <a class="sidebar-link {{ request()->routeIs('opening-inventory.*') ? 'active' : '' }}"
                           href="{{ route('opening-inventory.index') }}">{{ __('catalog.navigation.opening_inventory') }}</a>
                    @endcan
                    @can('purchases.view')
                        <a class="sidebar-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}"
                           href="{{ route('purchases.index') }}">{{ __('catalog.navigation.purchases') }}</a>
                    @endcan
                </nav>
            @endif
            <div class="mt-lg-auto border-top border-secondary pt-3">
                <div class="small mb-2">{{ auth()->user()->display_name }}</div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-light w-100" type="submit">
                        {{ __('catalog.navigation.logout') }}
                    </button>
                </form>
            </div>
        </aside>

        <main class="col-12 col-lg-9 col-xl-10 main-content px-3 px-lg-4 py-4">
            <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h1 class="h3 mb-1">@yield('heading')</h1>
                    @hasSection('breadcrumb')
                        <div class="text-secondary small">@yield('breadcrumb')</div>
                    @endif
                </div>
                <div class="text-secondary small">{{ auth()->user()->display_name }}</div>
            </header>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="status">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                            aria-label="{{ __('catalog.actions.close') }}"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <div class="fw-semibold mb-1">{{ __('catalog.messages.validation_error') }}</div>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
