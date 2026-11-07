<?php

namespace App\Support;

use App\Enums\BaseInventoryUnit;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;

class CatalogRules
{
    public static function baseUnitFor(ProductCategory $category): ?BaseInventoryUnit
    {
        return match (mb_strtolower(trim($category->name))) {
            'honey', 'nuts' => BaseInventoryUnit::Kg,
            'ghee' => BaseInventoryUnit::Can,
            default => null,
        };
    }

    public static function unitsByCategoryId(Collection $categories): array
    {
        $units = [];

        foreach ($categories as $category) {
            $units[$category->getKey()] = self::baseUnitFor($category)?->value;
        }

        return $units;
    }
}
