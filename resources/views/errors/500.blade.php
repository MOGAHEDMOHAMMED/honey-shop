@extends('layouts.guest')

@section('title', __('errors.server_error_title'))
@section('content')
    <main class="card page-card w-100 text-center" style="max-width: 560px">
        <div class="card-body p-5">
            <h1 class="h3">{{ __('errors.server_error_title') }}</h1>
            <p class="text-secondary">{{ __('errors.server_error_message') }}</p>
            <a class="btn btn-primary" href="{{ route('login') }}">{{ __('catalog.pages.login') }}</a>
        </div>
    </main>
@endsection
