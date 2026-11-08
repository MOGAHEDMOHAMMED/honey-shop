<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OpeningInventory\OpeningInventoryController;
use App\Http\Controllers\Purchases\PurchaseController;
use App\Http\Controllers\Sales\SalesController;
use App\Http\Controllers\Suppliers\SupplierController;
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
        foreach ([
            ['catalog.products.view', 'products.index'],
            ['catalog.categories.view', 'categories.index'],
            ['suppliers.view', 'suppliers.index'],
            ['opening-inventory.view', 'opening-inventory.index'],
            ['purchases.view', 'purchases.index'],
            ['customers.view', 'customers.index'],
            ['sales.view', 'sales.index'],
        ] as [$permission, $route]) {
            if (auth()->user()->can($permission)) {
                return redirect()->route($route);
            }
        }

        abort(403);
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

    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->middleware('can:suppliers.view')->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])
        ->middleware('can:suppliers.manage')->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware('can:suppliers.manage')->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->middleware('can:suppliers.view')->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->middleware('can:suppliers.manage')->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware('can:suppliers.manage')->name('suppliers.update');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('can:customers.view')->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])
        ->middleware('can:customers.manage')->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('can:customers.manage')->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('can:customers.view')->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->middleware('can:customers.manage')->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('can:customers.manage')->name('customers.update');

    Route::get('/sales', [SalesController::class, 'index'])
        ->middleware('can:sales.view')->name('sales.index');
    Route::get('/sales/create', [SalesController::class, 'create'])
        ->middleware('can:sales.manage')->name('sales.create');
    Route::post('/sales', [SalesController::class, 'store'])
        ->middleware('can:sales.manage')->name('sales.store');
    Route::get('/sales/{sale}', [SalesController::class, 'show'])
        ->middleware('can:sales.view')->name('sales.show');
    Route::get('/sales/{sale}/edit', [SalesController::class, 'edit'])
        ->middleware('can:sales.manage')->name('sales.edit');
    Route::put('/sales/{sale}', [SalesController::class, 'update'])
        ->middleware('can:sales.manage')->name('sales.update');
    Route::post('/sales/{sale}/approve', [SalesController::class, 'approve'])
        ->middleware('can:sales.approve')->name('sales.approve');

    Route::get('/opening-inventory', [OpeningInventoryController::class, 'index'])
        ->middleware('can:opening-inventory.view')->name('opening-inventory.index');
    Route::get('/opening-inventory/create', [OpeningInventoryController::class, 'create'])
        ->middleware('can:opening-inventory.manage')->name('opening-inventory.create');
    Route::post('/opening-inventory', [OpeningInventoryController::class, 'store'])
        ->middleware('can:opening-inventory.manage')->name('opening-inventory.store');
    Route::get('/opening-inventory/{openingInventory}', [OpeningInventoryController::class, 'show'])
        ->middleware('can:opening-inventory.view')->name('opening-inventory.show');
    Route::get('/opening-inventory/{openingInventory}/edit', [OpeningInventoryController::class, 'edit'])
        ->middleware('can:opening-inventory.manage')->name('opening-inventory.edit');
    Route::put('/opening-inventory/{openingInventory}', [OpeningInventoryController::class, 'update'])
        ->middleware('can:opening-inventory.manage')->name('opening-inventory.update');
    Route::post('/opening-inventory/{openingInventory}/approve', [OpeningInventoryController::class, 'approve'])
        ->middleware('can:opening-inventory.manage')->name('opening-inventory.approve');

    Route::get('/purchases', [PurchaseController::class, 'index'])
        ->middleware('can:purchases.view')->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])
        ->middleware('can:purchases.manage')->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])
        ->middleware('can:purchases.manage')->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])
        ->middleware('can:purchases.view')->name('purchases.show');
    Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])
        ->middleware('can:purchases.manage')->name('purchases.edit');
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])
        ->middleware('can:purchases.manage')->name('purchases.update');
    Route::post('/purchases/{purchase}/approve', [PurchaseController::class, 'approve'])
        ->middleware('can:purchases.approve')->name('purchases.approve');
});
