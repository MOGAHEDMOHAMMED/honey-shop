<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', [HealthController::class, 'live'])
    ->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])
    ->name('health.ready');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
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
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('/categories', [CategoryController::class, 'index'])
        ->middleware('can:catalog.categories.view')
        ->name('categories.index');
    Route::patch('/categories/{category}/status', [
        CategoryController::class,
        'updateStatus',
    ])->middleware('can:catalog.categories.manage')->name('categories.status');

    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('can:catalog.products.view')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('can:catalog.products.manage')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('can:catalog.products.manage')->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('can:catalog.products.view')->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('can:catalog.products.manage')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('can:catalog.products.manage')->name('products.update');
});
