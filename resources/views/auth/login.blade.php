@extends('layouts.guest')

@section('title', __('catalog.pages.login'))

@section('content')
<main class="card page-card w-100" style="max-width: 430px">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="app-brand-mark mx-auto mb-3">ع</div>
            <h1 class="h4 mb-1">{{ __('catalog.app_name') }}</h1>
            <p class="text-secondary mb-0">{{ __('catalog.pages.login') }}</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">{{ __('catalog.fields.username') }}</label>
                <input id="username" name="username" type="text"
                       class="form-control @error('username') is-invalid @enderror"
                       value="{{ old('username') }}" autocomplete="username" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">{{ __('catalog.fields.password') }}</label>
                <input id="password" name="password" type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                {{ __('catalog.actions.login') }}
            </button>
        </form>
    </div>
</main>
@endsection
