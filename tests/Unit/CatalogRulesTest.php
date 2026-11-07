<?php

namespace Tests\Unit;

use App\Enums\BaseInventoryUnit;
use App\Models\ProductCategory;
use App\Support\CatalogRules;
use Tests\TestCase;

class CatalogRulesTest extends TestCase
{
    public function test_only_the_three_supported_category_unit_mappings_are_returned(): void
    {
        $this->assertSame(
            BaseInventoryUnit::Kg,
            CatalogRules::baseUnitFor(new ProductCategory(['name' => 'Honey'])),
        );
        $this->assertSame(
            BaseInventoryUnit::Can,
            CatalogRules::baseUnitFor(new ProductCategory(['name' => 'Ghee'])),
        );
        $this->assertSame(
            BaseInventoryUnit::Kg,
            CatalogRules::baseUnitFor(new ProductCategory(['name' => 'Nuts'])),
        );
        $this->assertNull(
            CatalogRules::baseUnitFor(new ProductCategory(['name' => 'Unapproved'])),
        );
    }
}
