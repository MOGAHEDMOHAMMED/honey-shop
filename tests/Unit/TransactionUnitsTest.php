<?php

namespace Tests\Unit;

use App\Enums\BaseInventoryUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\TransactionUnits;
use PHPUnit\Framework\TestCase;

class TransactionUnitsTest extends TestCase
{
    public function test_honey_gallons_convert_to_seven_base_kilograms(): void
    {
        $product = $this->product('Honey', BaseInventoryUnit::Kg);

        $this->assertSame('7.000000', TransactionUnits::conversionFactor($product, 'GALLON'));
        $this->assertSame('14.000', TransactionUnits::baseQuantity('2.000', '7.000000'));
        $this->assertSame('0.007', TransactionUnits::baseQuantity('0.001', '7.000000'));
    }

    public function test_only_category_approved_input_units_are_accepted(): void
    {
        $this->assertSame(
            '1.000000',
            TransactionUnits::conversionFactor($this->product('Ghee', BaseInventoryUnit::Can), 'CAN'),
        );
        $this->assertSame(
            '1.000000',
            TransactionUnits::conversionFactor($this->product('Nuts', BaseInventoryUnit::Kg), 'KG'),
        );
        $this->assertNull(
            TransactionUnits::conversionFactor($this->product('Ghee', BaseInventoryUnit::Can), 'KG'),
        );
        $this->assertNull(
            TransactionUnits::conversionFactor($this->product('Nuts', BaseInventoryUnit::Kg), 'GALLON'),
        );
        $this->assertNull(
            TransactionUnits::conversionFactor($this->product('Honey', BaseInventoryUnit::Kg), 'UNIT'),
        );
    }

    private function product(string $category, BaseInventoryUnit $unit): Product
    {
        $product = new Product(['base_unit' => $unit]);
        $product->setRelation('category', new ProductCategory(['name' => $category]));

        return $product;
    }
}
