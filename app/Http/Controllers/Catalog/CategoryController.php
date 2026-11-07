<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CategoryStatusRequest;
use App\Models\ProductCategory;
use App\Services\Catalog\CategoryCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($validated['search'] ?? '');

        $categories = ProductCategory::query()
            ->withCount('products')
            ->when($search !== '', fn ($query) => $query->where('name', 'ilike', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('modules.catalog.categories.index', compact('categories', 'search'));
    }

    public function updateStatus(
        CategoryStatusRequest $request,
        ProductCategory $category,
        CategoryCatalogService $service,
    ): RedirectResponse {
        $service->setActive($category, (bool) $request->validated('is_active'));

        return back()->with('success', __('catalog.messages.category_status_updated'));
    }
}
