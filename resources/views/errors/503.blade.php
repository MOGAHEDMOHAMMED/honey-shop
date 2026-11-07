@extends('layouts.guest')

@section('title', __('errors.unavailable_title'))
@section('content')
    <main class="card page-card w-100 text-center" style="max-width: 560px">
        <div class="card-body p-5">
            <h1 class="h3">{{ __('errors.unavailable_title') }}</h1>
            <p class="text-secondary">{{ __('errors.unavailable_message') }}</p>
        </div>
    </main>
@endsection
