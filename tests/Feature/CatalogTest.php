<?php

namespace Tests\Feature;

use App\Enums\BaseInventoryUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = $this->createAdministrator();
        $this->actingAs($this->administrator);
    }

    public function test_category_list_uses_the_existing_three_supported_categories(): void
    {
        $response = $this->get(route('categories.index'))->assertOk();

        foreach (['Honey', 'Ghee', 'Nuts'] as $name) {
            $response->assertSee(__('catalog.categories.'.$name));
        }

        $this->assertSame(3, ProductCategory::query()->count());
    }

    public function test_categories_can_be_searched_and_deactivated_without_active_products(): void
    {
        $honey = ProductCategory::query()->where('name', 'Honey')->firstOrFail();

        $this->get(route('categories.index', ['search' => 'Honey']))
            ->assertOk()
            ->assertSee(__('catalog.categories.Honey'))
            ->assertDontSee(__('catalog.categories.Ghee'));

        $this->patch(route('categories.status', $honey), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($honey->fresh()->is_active);
    }

    public function test_categories_with_active_products_cannot_be_deactivated(): void
    {
        $honey = ProductCategory::query()->where('name', 'Honey')->firstOrFail();
        $this->createProduct($honey, 'Raw Honey');

        $this->from(route('categories.index'))
            ->patch(route('categories.status', $honey), ['is_active' => '0'])
            ->assertSessionHasErrors('category');

        $this->assertTrue($honey->fresh()->is_active);
    }

    public function test_inactive_category_cannot_receive_an_active_product(): void
    {
        $category = ProductCategory::query()->where('name', 'Honey')->firstOrFail();
        $product = $this->createProduct($category, 'Mountain Honey');
        $inactiveData = $this->validProductData($category, 'Mountain Honey');
        $inactiveData['is_active'] = '0';

        $this->put(route('products.update', $product), $inactiveData)->assertRedirect();
        $this->patch(route('categories.status', $category), ['is_active' => '0'])->assertRedirect();

        $activeData = $this->validProductData($category, 'Mountain Honey');
        $this->from(route('products.edit', $product))
            ->put(route('products.update', $product), $activeData)
            ->assertSessionHasErrors('category_id');

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_products_derive_base_units_and_yer_pricing_from_the_existing_schema(): void
    {
        $expected = [
            'Honey' => BaseInventoryUnit::Kg,
            'Ghee' => BaseInventoryUnit::Can,
            'Nuts' => BaseInventoryUnit::Kg,
        ];

        foreach ($expected as $categoryName => $unit) {
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();
            $this->post(route('products.store'), $this->validProductData(
                $category,
                'Product '.$categoryName,
                ['base_unit' => 'UNIT'],
            ))->assertRedirect();

            $product = Product::query()->where('name', 'Product '.$categoryName)->firstOrFail();
            $this->assertSame($unit, $product->base_unit);
            $this->assertSame('125.5000', $product->selling_price);
            $this->assertSame('0.150000', $product->max_discount_rate);
        }

        $this->assertSame(0, DB::table('inventory_balances')->count());
    }

    public function test_product_search_and_category_filter_use_real_database_records(): void
    {
        $honey = ProductCategory::query()->where('name', 'Honey')->firstOrFail();
        $ghee = ProductCategory::query()->where('name', 'Ghee')->firstOrFail();
        $this->createProduct($honey, 'Mountain Honey', ['sku' => 'HNY-01']);
        $this->createProduct($ghee, 'Clarified Ghee', ['sku' => 'GHE-01']);

        $this->get(route('products.index', ['search' => 'Mountain']))
            ->assertOk()
            ->assertSee('Mountain Honey')
            ->assertDontSee('Clarified Ghee');

        $this->get(route('products.index', ['category' => $ghee->id]))
            ->assertOk()
            ->assertSee('Clarified Ghee')
            ->assertDontSee('Mountain Honey');
    }

    public function test_product_updates_do_not_change_the_read_only_inventory_balance(): void
    {
        $category = ProductCategory::query()->where('name', 'Honey')->firstOrFail();
        $product = $this->createProduct($category, 'Mountain Honey');

        DB::table('inventory_balances')->insert([
            'product_id' => $product->getKey(),
            'quantity' => 12.500,
            'updated_at' => now(),
        ]);

        $data = $this->validProductData($category, 'Mountain Honey Updated');
        $data['selling_price'] = '140.2500';
        $this->put(route('products.update', $product), $data)->assertRedirect();

        $this->assertSame(
            '12.500',
            DB::table('inventory_balances')
                ->where('product_id', $product->getKey())
                ->value('quantity'),
        );
        $this->assertSame('140.2500', $product->fresh()->selling_price);
    }

    public function test_a_product_with_inventory_cannot_be_reclassified_and_cannot_be_deleted(): void
    {
        $honey = ProductCategory::query()->where('name', 'Honey')->firstOrFail();
        $ghee = ProductCategory::query()->where('name', 'Ghee')->firstOrFail();
        $product = $this->createProduct($honey, 'Mountain Honey');

        DB::table('inventory_balances')->insert([
            'product_id' => $product->getKey(),
            'quantity' => 1,
            'updated_at' => now(),
        ]);

        $data = $this->validProductData($ghee, 'Mountain Honey');
        $this->from(route('products.edit', $product))
            ->put(route('products.update', $product), $data)
            ->assertSessionHasErrors('category_id');

        $this->delete(route('products.show', $product))->assertStatus(405);
        $this->assertDatabaseHas('products', ['id' => $product->getKey()]);
    }

    public function test_invalid_prices_and_discount_percentages_are_rejected(): void
    {
        $category = ProductCategory::query()->where('name', 'Honey')->firstOrFail();

        $this->from(route('products.create'))
            ->post(route('products.store'), $this->validProductData(
                $category,
                'Invalid Product',
                ['selling_price' => '-1', 'max_discount_percentage' => '100.01'],
            ))
            ->assertSessionHasErrors(['selling_price', 'max_discount_percentage']);

        $this->assertDatabaseMissing('products', ['name' => 'Invalid Product']);
    }

    public function test_product_details_show_real_stock_and_yer_price(): void
    {
        $category = ProductCategory::query()->where('name', 'Ghee')->firstOrFail();
        $product = $this->createProduct($category, 'Clarified Ghee');

        DB::table('inventory_balances')->insert([
            'product_id' => $product->getKey(),
            'quantity' => 3.250,
            'updated_at' => now(),
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee(__('catalog.units.CAN'))
            ->assertSee('3.250')
            ->assertSee(__('catalog.currency'));
    }

    private function createAdministrator(): User
    {
        $user = User::query()->create([
            'username' => 'catalog-admin',
            'display_name' => 'Catalog Administrator',
            'password_hash' => Hash::make('test-password'),
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', 'Administrator')->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }

    private function createProduct(
        ProductCategory $category,
        string $name,
        array $overrides = [],
    ): Product {
        $this->post(route('products.store'), $this->validProductData($category, $name, $overrides))
            ->assertRedirect();

        return Product::query()->where('name', $name)->firstOrFail();
    }

    private function validProductData(
        ProductCategory $category,
        string $name,
        array $overrides = [],
    ): array {
        return array_replace([
            'sku' => null,
            'name' => $name,
            'category_id' => $category->getKey(),
            'minimum_stock' => '0.500',
            'selling_price' => '125.5000',
            'max_discount_percentage' => '15',
            'is_active' => '1',
            'notes' => '',
        ], $overrides);
    }
}
