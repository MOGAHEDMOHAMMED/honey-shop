<?php

namespace Tests\Feature;

use App\Enums\BaseInventoryUnit;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesTest extends TestCase
{
    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = $this->createUserWithRole('Administrator');
        $this->actingAs($this->administrator);
    }

    public function test_customer_management_and_direct_sale_approval_update_balance_and_stock_once(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'عميل الاختبار',
            'phone' => '777123456',
            'country' => 'اليمن',
            'city' => 'صنعاء',
            'is_active' => '1',
        ])->assertRedirect();
        $customer = Customer::query()->where('phone', '777123456')->sole();

        $this->get(route('customers.index', ['search' => '777123456']))
            ->assertOk()
            ->assertSee('عميل الاختبار');
        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('0 ر.ي.');

        $product = $this->product('Honey', 'سدر');
        $this->approveOpeningStock([[
            'product_id' => $product->getKey(),
            'entered_quantity' => '5.000',
            'input_unit' => 'KG',
        ]]);
        $this->post(route('sales.store'), $this->saleData($customer, [[
            'product_id' => $product->getKey(),
            'entered_quantity' => '1.000',
            'input_unit' => 'KG',
            'unit_price' => '100.00',
            'discount_percentage' => '10',
        ]]) + ['invoice_total' => '0.01'])->assertRedirect();

        $sale = DB::table('sales_invoices')->where('customer_id', $customer->getKey())->sole();
        $this->assertSame('90.00', $sale->final_total);
        $this->from(route('sales.show', $sale->transaction_id))
            ->post(route('sales.approve', $sale->transaction_id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $approved = DB::table('sales_invoices')->where('transaction_id', $sale->transaction_id)->first();
        $this->assertSame('741', $approved->invoice_number);
        $this->assertSame('honey', $approved->series_category);
        $this->assertSame('approved', DB::table('business_transactions')
            ->where('id', $sale->transaction_id)->value('status'));
        $this->assertSame('4.000', DB::table('inventory_balances')
            ->where('product_id', $product->getKey())->value('quantity'));
        $this->assertDatabaseHas('customer_account_entries', [
            'transaction_id' => $sale->transaction_id,
            'source_sale_transaction_id' => $sale->transaction_id,
            'customer_id' => $customer->getKey(),
            'debit' => '90.00',
            'credit' => '0.00',
        ]);
        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('741')
            ->assertSee('90 ر.ي.');
        $this->assertSame(1, DB::table('inventory_movements')
            ->where('transaction_id', $sale->transaction_id)->count());

        try {
            DB::transaction(fn () => DB::table('sales_invoices')
                ->where('transaction_id', $sale->transaction_id)
                ->update(['invoice_number' => '999999']));
            $this->fail('Approved sales invoice numbers must not be changed.');
        } catch (QueryException $exception) {
            $this->assertSame('P0001', $exception->getCode());
        }

        $this->from(route('sales.show', $sale->transaction_id))
            ->post(route('sales.approve', $sale->transaction_id))
            ->assertSessionHasErrors('sale');
        $this->assertSame('4.000', DB::table('inventory_balances')
            ->where('product_id', $product->getKey())->value('quantity'));
        $this->assertSame(1, DB::table('customer_account_entries')
            ->where('source_sale_transaction_id', $sale->transaction_id)->count());
    }

    public function test_mixed_category_sales_require_an_explicit_series_before_approval(): void
    {
        $customer = $this->customer();
        $honey = $this->product('Honey', 'Mixed Honey');
        $ghee = $this->product('Ghee', 'Mixed Ghee');
        $this->approveOpeningStock([
            ['product_id' => $honey->getKey(), 'entered_quantity' => '2.000', 'input_unit' => 'KG'],
            ['product_id' => $ghee->getKey(), 'entered_quantity' => '2.000', 'input_unit' => 'CAN'],
        ]);
        $this->post(route('sales.store'), $this->saleData($customer, [
            ['product_id' => $honey->getKey(), 'entered_quantity' => '1.000', 'input_unit' => 'KG', 'unit_price' => '10.00'],
            ['product_id' => $ghee->getKey(), 'entered_quantity' => '1.000', 'input_unit' => 'CAN', 'unit_price' => '10.00'],
        ]))->assertRedirect();
        $sale = DB::table('sales_invoices')->where('customer_id', $customer->getKey())->sole();

        $this->from(route('sales.show', $sale->transaction_id))
            ->post(route('sales.approve', $sale->transaction_id))
            ->assertSessionHasErrors('series_category');
        $this->assertSame(0, (int) DB::table('sales_invoice_counters')
            ->where('series_category', 'honey')->value('last_issued_suffix'));

        $this->post(route('sales.approve', $sale->transaction_id), ['series_category' => 'nuts'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('941', DB::table('sales_invoices')
            ->where('transaction_id', $sale->transaction_id)->value('invoice_number'));
    }

    public function test_external_sale_includes_customer_delivery_charge_and_marks_shipment_on_approval(): void
    {
        $customer = $this->customer();
        $product = $this->product('Nuts', 'External Sale Nuts');
        $this->approveOpeningStock([[
            'product_id' => $product->getKey(),
            'entered_quantity' => '2.000',
            'input_unit' => 'KG',
        ]]);
        $this->post(route('sales.store'), [
            'customer_id' => $customer->getKey(),
            'sales_channel' => 'external',
            'document_date' => '2026-11-07',
            'delivery_charge' => '5.00',
            'delivery_cost_bearer' => 'customer',
            'lines' => [[
                'product_id' => $product->getKey(),
                'entered_quantity' => '1.000',
                'input_unit' => 'KG',
                'unit_price' => '10.00',
            ]],
        ])->assertRedirect();
        $sale = DB::table('sales_invoices')->where('customer_id', $customer->getKey())->sole();
        $this->assertSame('15.00', $sale->final_total);

        $this->post(route('sales.approve', $sale->transaction_id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('shipped', DB::table('sales_invoices')
            ->where('transaction_id', $sale->transaction_id)->value('order_status'));
        $this->assertDatabaseHas('customer_account_entries', [
            'source_sale_transaction_id' => $sale->transaction_id,
            'debit' => '15.00',
        ]);
    }

    public function test_sales_lines_apply_half_up_rounding_before_discount(): void
    {
        $customer = $this->customer();
        $product = $this->product('Nuts', 'Rounding Nuts');
        $product->update(['selling_price' => '0.50', 'max_discount_rate' => '0.500000']);
        $this->post(route('sales.store'), $this->saleData($customer, [[
            'product_id' => $product->getKey(),
            'entered_quantity' => '0.010',
            'input_unit' => 'KG',
            'unit_price' => '0.50',
            'discount_percentage' => '50',
        ]]))->assertRedirect();
        $sale = DB::table('sales_invoices')->where('customer_id', $customer->getKey())->sole();
        $line = DB::table('sales_lines')->where('transaction_id', $sale->transaction_id)->sole();

        $this->assertSame('0.01', $line->discount_amount);
        $this->assertSame('0.00', $line->line_total);
        $this->assertSame('0.01', $sale->products_subtotal);
        $this->assertSame('0.01', $sale->discounts_total);
        $this->assertSame('0.00', $sale->final_total);
    }

    public function test_sales_reject_discounts_above_product_limit_and_undersupplied_stock(): void
    {
        $customer = $this->customer();
        $product = $this->product('Nuts', 'Limited Discount Nuts');

        $this->from(route('sales.create'))
            ->post(route('sales.store'), $this->saleData($customer, [[
                'product_id' => $product->getKey(),
                'entered_quantity' => '1.000',
                'input_unit' => 'KG',
                'unit_price' => '10.00',
                'discount_percentage' => '15.0001',
            ]]))
            ->assertSessionHasErrors('lines.0.discount_percentage');

        $this->post(route('sales.store'), $this->saleData($customer, [[
            'product_id' => $product->getKey(),
            'entered_quantity' => '1.000',
            'input_unit' => 'KG',
            'unit_price' => '10.00',
        ]]))->assertRedirect();
        $sale = DB::table('sales_invoices')->where('customer_id', $customer->getKey())->sole();
        $this->from(route('sales.show', $sale->transaction_id))
            ->post(route('sales.approve', $sale->transaction_id))
            ->assertSessionHasErrors('lines');

        $this->assertSame('draft', DB::table('business_transactions')
            ->where('id', $sale->transaction_id)->value('status'));
        $this->assertSame(0, (int) DB::table('sales_invoice_counters')
            ->where('series_category', 'nuts')->value('last_issued_suffix'));
        $this->assertDatabaseMissing('customer_account_entries', [
            'source_sale_transaction_id' => $sale->transaction_id,
        ]);
    }

    public function test_sales_role_can_manage_sales_while_inventory_role_cannot(): void
    {
        $salesUser = $this->createUserWithRole('Sales User');
        $this->actingAs($salesUser)
            ->get(route('customers.create'))
            ->assertOk();
        $this->actingAs($salesUser)
            ->get(route('sales.create'))
            ->assertOk();

        $inventoryUser = $this->createUserWithRole('Inventory and Purchasing User');
        $this->actingAs($inventoryUser)
            ->get(route('customers.index'))
            ->assertForbidden();
        $this->actingAs($inventoryUser)
            ->get(route('sales.index'))
            ->assertForbidden();
    }

    private function approveOpeningStock(array $lines): void
    {
        $this->post(route('opening-inventory.store'), [
            'effective_at' => '2026-11-07',
            'notes' => 'Sales test stock',
            'lines' => $lines,
        ])->assertRedirect();
        $transactionId = DB::table('opening_inventory_lines')
            ->orderByDesc('id')
            ->value('transaction_id');
        $this->post(route('opening-inventory.approve', $transactionId))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    private function saleData(Customer $customer, array $lines): array
    {
        return [
            'customer_id' => $customer->getKey(),
            'sales_channel' => 'direct',
            'document_date' => '2026-11-07',
            'lines' => $lines,
        ];
    }

    private function customer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Sales Test Customer',
            'is_active' => true,
        ]);
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
            'selling_price' => '100.00',
            'max_discount_rate' => '0.150000',
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::query()->create([
            'username' => 'sales-test-'.Str::uuid(),
            'display_name' => $roleName,
            'password_hash' => Hash::make('test-password'),
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
