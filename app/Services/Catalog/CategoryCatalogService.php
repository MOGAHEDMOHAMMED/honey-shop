<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CategoryCatalogService
{
    public function setActive(ProductCategory $category, bool $isActive): ProductCategory
    {
        $updatedCategory = DB::transaction(function () use ($category, $isActive): ProductCategory {
            $category = ProductCategory::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $isActive && $category->products()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'category' => __('catalog.errors.category_has_active_products'),
                ]);
            }

            $category->is_active = $isActive;
            $category->save();

            return $category;
        });

        Log::info('Catalog category status changed', [
            'category_id' => $updatedCategory->getKey(),
            'is_active' => $isActive,
            'actor_user_id' => auth()->id(),
        ]);

        return $updatedCategory;
    }
}
