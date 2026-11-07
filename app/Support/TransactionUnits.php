<?php

namespace App\Support;

use App\Models\Product;

class TransactionUnits
{
    public static function conversionFactor(Product $product, string $inputUnit): ?string
    {
        $category = mb_strtolower(trim((string) $product->category?->name));
        $baseUnit = $product->base_unit?->value;

        return match ([$category, $baseUnit, $inputUnit]) {
            ['honey', 'KG', 'KG'], ['ghee', 'CAN', 'CAN'], ['nuts', 'KG', 'KG'] => '1.000000',
            ['honey', 'KG', 'GALLON'] => '7.000000',
            default => null,
        };
    }

    public static function baseQuantity(string $enteredQuantity, string $conversionFactor): string
    {
        return bcmul($enteredQuantity, $conversionFactor, 3);
    }
}
