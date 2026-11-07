<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use App\Support\CatalogRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductCatalogService
{
    public function create(array $data): Product
    {
        return $this->persist(new Product, $data);
    }

    public function update(Product $product, array $data): Product
    {
        return $this->persist($product, $data);
    }

    private function persist(Product $product, array $data): Product
    {
        $wasNew = ! $product->exists;

        try {
            $savedProduct = DB::transaction(function () use ($product, $data): Product {
                if ($product->exists) {
                    $product = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $category = ProductCategory::query()
                    ->whereKey($data['category_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $category->is_active
                    && (! $product->exists
                        || $product->category_id !== $category->getKey()
                        || (bool) $data['is_active'])) {
                    throw ValidationException::withMessages([
                        'category_id' => __('catalog.errors.category_inactive'),
                    ]);
                }

                $baseUnit = CatalogRules::baseUnitFor($category);
                if ($baseUnit === null) {
                    throw ValidationException::withMessages([
                        'category_id' => __('catalog.errors.unsupported_category'),
                    ]);
                }

                if ($product->exists
                    && $product->category_id !== $category->getKey()
                    && $this->hasInventoryOrTransactionHistory($product)) {
                    throw ValidationException::withMessages([
                        'category_id' => __('catalog.errors.category_has_history'),
                    ]);
                }

                $previousPrice = $product->exists ? $product->selling_price : null;
                $previousDiscountRate = $product->exists ? $product->max_discount_rate : null;

                $product->fill([
                    'sku' => $data['sku'] ?: null,
                    'name' => $data['name'],
                    'category_id' => $category->getKey(),
                    'base_unit' => $baseUnit,
                    'minimum_stock' => $data['minimum_stock'] ?? null,
                    'is_active' => (bool) $data['is_active'],
                    'notes' => $data['notes'] ?: null,
                    'selling_price' => $data['selling_price'],
                    'max_discount_rate' => bcdiv((string) $data['max_discount_percentage'], '100', 6),
                ]);
                $product->save();

                $priceChanged = $previousPrice === null
                    || bccomp((string) $previousPrice, (string) $product->selling_price, 2) !== 0
                    || bccomp((string) $previousDiscountRate, (string) $product->max_discount_rate, 6) !== 0;

                if ($priceChanged) {
                    ProductPriceHistory::query()->create([
                        'product_id' => $product->getKey(),
                        'previous_selling_price' => $previousPrice,
                        'new_selling_price' => $product->selling_price,
                        'previous_max_discount_rate' => $previousDiscountRate,
                        'new_max_discount_rate' => $product->max_discount_rate,
                        'actor_user_id' => auth()->id(),
                        'reason' => ($data['price_change_reason'] ?? null) ?: null,
                    ]);
                }

                return $product;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages([
                    'sku' => __('catalog.errors.duplicate_sku'),
                ]);
            }

            throw $exception;
        }

        Log::info('Catalog product saved', [
            'product_id' => $savedProduct->getKey(),
            'created' => $wasNew,
            'actor_user_id' => auth()->id(),
        ]);

        return $savedProduct;
    }

    private function hasInventoryOrTransactionHistory(Product $product): bool
    {
        foreach ([
            'inventory_balances',
            'inventory_movements',
            'purchase_lines',
            'sales_lines',
            'opening_inventory_lines',
            'stock_adjustment_lines',
            'damaged_goods',
        ] as $table) {
            if (DB::table($table)->where('product_id', $product->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
