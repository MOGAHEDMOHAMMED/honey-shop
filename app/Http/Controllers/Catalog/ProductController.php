<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use App\Services\Catalog\ProductCatalogService;
use App\Support\CatalogRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'uuid'],
        ]);
        $search = trim($validated['search'] ?? '');
        $categoryId = $validated['category'] ?? null;

        $products = Product::query()
            ->with(['category', 'inventoryBalance'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('sku', 'ilike', '%'.$search.'%');
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = ProductCategory::query()->orderBy('name')->get();

        return view(
            'modules.catalog.products.index',
            compact('products', 'categories', 'search', 'categoryId'),
        );
    }

    public function create(): View
    {
        $categories = ProductCategory::query()->where('is_active', true)->orderBy('name')->get();
        $product = new Product(['is_active' => true]);
        $categoryUnits = CatalogRules::unitsByCategoryId($categories);

        return view('modules.catalog.products.create', compact('categories', 'categoryUnits', 'product'));
    }

    public function store(ProductRequest $request, ProductCatalogService $service): RedirectResponse
    {
        $product = $service->create($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('catalog.messages.product_created'));
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'inventoryBalance']);
        $priceHistory = null;
        if (auth()->user()->can('catalog.products.price-history.view')) {
            $priceHistory = ProductPriceHistory::query()
                ->where('product_id', $product->getKey())
                ->with('actor')
                ->orderByDesc('created_at')
                ->paginate(10);
        }

        return view('modules.catalog.products.show', compact('product', 'priceHistory'));
    }

    public function edit(Product $product): View
    {
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->orWhereKey($product->category_id)
            ->orderBy('name')
            ->get();
        $categoryUnits = CatalogRules::unitsByCategoryId($categories);

        return view('modules.catalog.products.edit', compact('categories', 'categoryUnits', 'product'));
    }

    public function update(
        ProductRequest $request,
        Product $product,
        ProductCatalogService $service,
    ): RedirectResponse {
        $service->update($product, $request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('catalog.messages.product_updated'));
    }
}
