<?php

namespace Tests\Feature;

use App\Enums\BaseInventoryUnit;
use App\Models\OpeningInventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OpeningInventoryTest extends TestCase
{
    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = $this->createUserWithRole('Administrator');
        $this->actingAs($this->administrator);
    }

    public function test_opening_inventory_draft_preserves_units_without_changing_stock(): void
    {
        $honey = $this->product('Honey', 'Opening Honey');
        $ghee = $this->product('Ghee', 'Opening Ghee');
        $nuts = $this->product('Nuts', 'Opening Nuts');
        $this->post(route('opening-inventory.store'), $this->openingData([
            ['product_id' => $honey->getKey(), 'entered_quantity' => '2.000', 'input_unit' => 'GALLON'],
            ['product_id' => $ghee->getKey(), 'entered_quantity' => '1.500', 'input_unit' => 'CAN'],
            ['product_id' => $nuts->getKey(), 'entered_quantity' => '0.500', 'input_unit' => 'KG'],
        ]))->assertRedirect();

        $document = OpeningInventory::query()->sole();
        $honeyLine = $document->lines()->where('product_id', $honey->getKey())->firstOrFail();
        $this->assertSame('draft', $document->businessTransaction()->value('status'));
        $this->assertSame('2.000', $honeyLine->entered_quantity);
        $this->assertSame('GALLON', $honeyLine->input_unit);
        $this->assertSame('7.000000', $honeyLine->conversion_factor);
        $this->assertSame('14.000', $honeyLine->quantity);
        $this->assertSame(0, DB::table('inventory_movements')->count());
        $this->assertSame(0, DB::table('inventory_balances')->count());
    }

    public function test_approval_posts_converted_opening_stock_exactly_once(): void
    {
        $honey = $this->product('Honey', 'Opening Honey');
        $ghee = $this->product('Ghee', 'Opening Ghee');
        $nuts = $this->product('Nuts', 'Opening Nuts');
        $this->post(route('opening-inventory.store'), $this->openingData([
            ['product_id' => $honey->getKey(), 'entered_quantity' => '2.000', 'input_unit' => 'GALLON'],
            ['product_id' => $ghee->getKey(), 'entered_quantity' => '1.500', 'input_unit' => 'CAN'],
            ['product_id' => $nuts->getKey(), 'entered_quantity' => '0.500', 'input_unit' => 'KG'],
        ]))->assertRedirect();
        $document = OpeningInventory::query()->sole();

        $this->from(route('opening-inventory.show', $document))
            ->post(route('opening-inventory.approve', $document))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $document->businessTransaction()->value('status'));
        $this->assertSame('14.000', DB::table('inventory_balances')
            ->where('product_id', $honey->getKey())->value('quantity'));
        $this->assertSame('1.500', DB::table('inventory_balances')
            ->where('product_id', $ghee->getKey())->value('quantity'));
        $this->assertSame('0.500', DB::table('inventory_balances')
            ->where('product_id', $nuts->getKey())->value('quantity'));
        $this->assertSame(3, DB::table('inventory_movements')
            ->where('transaction_id', $document->transaction_id)->count());
        $this->assertSame(0, DB::table('supplier_account_entries')->count());

        $this->from(route('opening-inventory.show', $document))
            ->post(route('opening-inventory.approve', $document))
            ->assertSessionHasErrors('opening_inventory');
        $this->assertSame(3, DB::table('inventory_movements')
            ->where('transaction_id', $document->transaction_id)->count());
    }

    public function test_second_opening_balance_is_blocked_after_initial_approval(): void
    {
        $honey = $this->product('Honey', 'Opening Honey');
        $this->post(route('opening-inventory.store'), $this->openingData([
            ['product_id' => $honey->getKey(), 'entered_quantity' => '1.000', 'input_unit' => 'KG'],
        ]))->assertRedirect();
        $document = OpeningInventory::query()->sole();
        $this->post(route('opening-inventory.approve', $document))->assertRedirect();

        $this->from(route('opening-inventory.create'))
            ->post(route('opening-inventory.store'), $this->openingData([
                ['product_id' => $honey->getKey(), 'entered_quantity' => '1.000', 'input_unit' => 'KG'],
            ]))
            ->assertSessionHasErrors('opening_inventory');

        $this->assertSame(1, OpeningInventory::query()->count());
        $this->assertSame('1.000', DB::table('inventory_balances')
            ->where('product_id', $honey->getKey())->value('quantity'));
    }

    public function test_invalid_unit_and_unauthorized_opening_operations_are_rejected(): void
    {
        $nuts = $this->product('Nuts', 'Opening Nuts');
        $this->from(route('opening-inventory.create'))
            ->post(route('opening-inventory.store'), $this->openingData([
                ['product_id' => $nuts->getKey(), 'entered_quantity' => '1.000', 'input_unit' => 'GALLON'],
            ]))
            ->assertSessionHasErrors('lines.0.input_unit');

        $salesUser = $this->createUserWithRole('Sales User');
        $this->actingAs($salesUser)->get(route('opening-inventory.index'))->assertForbidden();
    }

    private function product(string $categoryName, string $name): Product
    {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        return Product::query()->create([
            'name' => $name,
            'category_id' => $category->getKey(),
            'base_unit' => $categoryName === 'Ghee'
                ? BaseInventoryUnit::Can
                : BaseInventoryUnit::Kg,
            'selling_price' => '10.00',
            'is_active' => true,
        ]);
    }

    private function openingData(array $lines): array
    {
        return [
            'effective_at' => '2026-11-07',
            'notes' => 'Initial physical count',
            'lines' => $lines,
        ];
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::query()->create([
            'username' => 'opening-'.strtolower(str_replace(' ', '-', $roleName)),
            'display_name' => $roleName,
            'password_hash' => Hash::make('test-password'),
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
