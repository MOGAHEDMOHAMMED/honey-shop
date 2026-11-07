@extends('layouts.guest')

@section('title', __('errors.forbidden_title'))
@section('content')
    <main class="card page-card w-100 text-center" style="max-width: 560px">
        <div class="card-body p-5">
            <h1 class="h3">{{ __('errors.forbidden_title') }}</h1>
            <p class="text-secondary">{{ __('errors.forbidden_message') }}</p>
            <a class="btn btn-primary" href="{{ url()->previous() }}">{{ __('catalog.actions.back') }}</a>
        </div>
    </main>
@endsection
