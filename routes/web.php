<?php

use Illuminate\Support\Facades\Route;

Route::get('/health/live', [App\Http\Controllers\HealthController::class, 'live'])
    ->name('health.live');
Route::get('/health/ready', [App\Http\Controllers\HealthController::class, 'ready'])
    ->name('health.ready');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', function () {
        $destination = auth()->user()->can('catalog.products.view')
            ? 'products.index'
            : 'categories.index';

        return redirect()->route($destination);
    })->name('home');
    Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('/categories', [App\Http\Controllers\Catalog\CategoryController::class, 'index'])
        ->middleware('can:catalog.categories.view')
        ->name('categories.index');
    Route::patch('/categories/{category}/status', [
        App\Http\Controllers\Catalog\CategoryController::class,
        'updateStatus',
    ])->middleware('can:catalog.categories.manage')->name('categories.status');

    Route::get('/products', [App\Http\Controllers\Catalog\ProductController::class, 'index'])
        ->middleware('can:catalog.products.view')->name('products.index');
    Route::get('/products/create', [App\Http\Controllers\Catalog\ProductController::class, 'create'])
        ->middleware('can:catalog.products.manage')->name('products.create');
    Route::post('/products', [App\Http\Controllers\Catalog\ProductController::class, 'store'])
        ->middleware('can:catalog.products.manage')->name('products.store');
    Route::get('/products/{product}', [App\Http\Controllers\Catalog\ProductController::class, 'show'])
        ->middleware('can:catalog.products.view')->name('products.show');
    Route::get('/products/{product}/edit', [App\Http\Controllers\Catalog\ProductController::class, 'edit'])
        ->middleware('can:catalog.products.manage')->name('products.edit');
    Route::put('/products/{product}', [App\Http\Controllers\Catalog\ProductController::class, 'update'])
        ->middleware('can:catalog.products.manage')->name('products.update');
});
