<?php

namespace Tests\Feature;

use App\Enums\BaseInventoryUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseLine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = $this->createUserWithRole('Administrator');
        $this->actingAs($this->administrator);
    }

    public function test_purchase_draft_preserves_original_unit_cost_and_does_not_change_stock(): void
    {
        $supplier = $this->supplier();
        $honey = $this->product('Honey', 'Raw Honey');

        $this->post(route('purchases.store'), $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '2.000',
            'input_unit' => 'GALLON',
            'purchase_unit_price' => '10.05',
        ]]))->assertRedirect();

        $invoice = PurchaseInvoice::query()->where('supplier_id', $supplier->getKey())->sole();
        $line = PurchaseLine::query()->where('transaction_id', $invoice->transaction_id)->sole();

        $this->assertSame('draft', $invoice->businessTransaction()->value('status'));
        $this->assertSame('SUP-HONEY-001', $invoice->supplier_invoice_number);
        $this->assertSame('PUR-'.strtoupper($invoice->transaction_id), $invoice->invoice_number);
        $this->assertSame('GALLON', $line->input_unit);
        $this->assertSame('2.000', $line->entered_quantity);
        $this->assertSame('7.000000', $line->conversion_factor);
        $this->assertSame('14.000', $line->base_quantity);
        $this->assertSame('10.05', $line->purchase_unit_price);
        $this->assertSame('1.44', $line->effective_base_unit_cost);
        $this->assertSame('20.10', $line->line_total);
        $this->assertSame('20.10', $invoice->invoice_total);
        $this->assertDatabaseMissing('inventory_balances', ['product_id' => $honey->getKey()]);
        $this->assertDatabaseMissing('supplier_account_entries', [
            'source_purchase_transaction_id' => $invoice->transaction_id,
        ]);
    }

    public function test_approval_posts_inventory_and_supplier_payable_once(): void
    {
        $supplier = $this->supplier();
        $honey = $this->product('Honey', 'Raw Honey');
        $this->post(route('purchases.store'), $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '2.000',
            'input_unit' => 'GALLON',
            'purchase_unit_price' => '10.05',
        ]]))->assertRedirect();
        $invoice = PurchaseInvoice::query()->where('supplier_id', $supplier->getKey())->sole();

        $this->from(route('purchases.show', $invoice))
            ->post(route('purchases.approve', $invoice))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $invoice->businessTransaction()->value('status'));
        $this->assertSame('14.000', DB::table('inventory_balances')
            ->where('product_id', $honey->getKey())->value('quantity'));
        $this->assertSame(1, DB::table('inventory_movements')
            ->where('transaction_id', $invoice->transaction_id)->count());
        $this->assertDatabaseHas('supplier_account_entries', [
            'transaction_id' => $invoice->transaction_id,
            'source_purchase_transaction_id' => $invoice->transaction_id,
            'supplier_id' => $supplier->getKey(),
            'debit' => '0.00',
            'credit' => '20.10',
        ]);

        $this->from(route('purchases.show', $invoice))
            ->post(route('purchases.approve', $invoice))
            ->assertSessionHasErrors('purchase');

        $this->assertSame(1, DB::table('inventory_movements')
            ->where('transaction_id', $invoice->transaction_id)->count());
        $this->assertSame(1, DB::table('supplier_account_entries')
            ->where('source_purchase_transaction_id', $invoice->transaction_id)->count());
        $this->assertSame('14.000', DB::table('inventory_balances')
            ->where('product_id', $honey->getKey())->value('quantity'));
    }

    public function test_purchase_rounds_each_line_before_summing_the_invoice(): void
    {
        $supplier = $this->supplier();
        $nuts = $this->product('Nuts', 'Almonds');
        $line = [
            'product_id' => $nuts->getKey(),
            'entered_quantity' => '0.500',
            'input_unit' => 'KG',
            'purchase_unit_price' => '0.01',
        ];

        $this->post(route('purchases.store'), $this->purchaseData($supplier, [$line, $line]))
            ->assertRedirect();
        $invoice = PurchaseInvoice::query()->where('supplier_id', $supplier->getKey())->sole();

        $this->assertSame('0.01', PurchaseLine::query()
            ->where('transaction_id', $invoice->transaction_id)->firstOrFail()->line_total);
        $this->assertSame('0.02', $invoice->invoice_total);

        $this->post(route('purchases.approve', $invoice))->assertRedirect();
        $this->assertSame('1.000', DB::table('inventory_balances')
            ->where('product_id', $nuts->getKey())->value('quantity'));
        $this->assertSame('0.02', DB::table('supplier_account_entries')
            ->where('source_purchase_transaction_id', $invoice->transaction_id)
            ->value('credit'));
    }

    public function test_draft_purchase_can_be_edited_and_recalculates_its_total(): void
    {
        $supplier = $this->supplier();
        $honey = $this->product('Honey', 'Raw Honey');
        $this->post(route('purchases.store'), $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '1.000',
            'input_unit' => 'KG',
            'purchase_unit_price' => '10.00',
        ]]))->assertRedirect();
        $invoice = PurchaseInvoice::query()->sole();

        $updatedData = $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '2.000',
            'input_unit' => 'GALLON',
            'purchase_unit_price' => '10.05',
        ]]);
        $updatedData['supplier_invoice_number'] = 'SUP-HONEY-UPDATED';

        $this->put(route('purchases.update', $invoice), $updatedData)->assertRedirect();

        $invoice->refresh();
        $line = $invoice->lines()->sole();
        $this->assertSame('SUP-HONEY-UPDATED', $invoice->supplier_invoice_number);
        $this->assertSame('14.000', $line->base_quantity);
        $this->assertSame('20.10', $invoice->invoice_total);
        $this->assertSame('draft', $invoice->businessTransaction()->value('status'));
        $this->assertDatabaseMissing('inventory_movements', [
            'transaction_id' => $invoice->transaction_id,
        ]);
        $this->assertDatabaseMissing('supplier_account_entries', [
            'source_purchase_transaction_id' => $invoice->transaction_id,
        ]);
    }

    public function test_approved_purchase_is_not_editable(): void
    {
        $supplier = $this->supplier();
        $honey = $this->product('Honey', 'Raw Honey');
        $this->post(route('purchases.store'), $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '1.000',
            'input_unit' => 'KG',
            'purchase_unit_price' => '10.00',
        ]]))->assertRedirect();
        $invoice = PurchaseInvoice::query()->sole();
        $this->post(route('purchases.approve', $invoice))->assertRedirect();

        $updatedData = $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '2.000',
            'input_unit' => 'KG',
            'purchase_unit_price' => '10.00',
        ]]);
        $this->from(route('purchases.edit', $invoice))
            ->put(route('purchases.update', $invoice), $updatedData)
            ->assertSessionHasErrors('purchase');

        $invoice->refresh();
        $this->assertSame('approved', $invoice->businessTransaction()->value('status'));
        $this->assertSame('10.00', $invoice->invoice_total);
        $this->assertSame('1.000', $invoice->lines()->sole()->entered_quantity);
        $this->assertSame(1, DB::table('inventory_movements')
            ->where('transaction_id', $invoice->transaction_id)->count());
    }

    public function test_purchase_approval_requires_its_specific_permission(): void
    {
        $supplier = $this->supplier();
        $honey = $this->product('Honey', 'Raw Honey');
        $this->post(route('purchases.store'), $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '1.000',
            'input_unit' => 'KG',
            'purchase_unit_price' => '10.00',
        ]]))->assertRedirect();
        $invoice = PurchaseInvoice::query()->sole();

        $role = Role::query()->create(['name' => 'Purchase Editor']);
        DB::table('role_permissions')->insert([
            ['role_id' => $role->getKey(), 'permission_code' => 'purchases.view'],
            ['role_id' => $role->getKey(), 'permission_code' => 'purchases.manage'],
        ]);
        $editor = $this->createUserWithRole('Purchase Editor');

        $this->actingAs($editor)
            ->post(route('purchases.approve', $invoice))
            ->assertForbidden();

        $this->assertSame('draft', $invoice->businessTransaction()->value('status'));
        $this->assertDatabaseMissing('inventory_movements', [
            'transaction_id' => $invoice->transaction_id,
        ]);
        $this->assertDatabaseMissing('supplier_account_entries', [
            'source_purchase_transaction_id' => $invoice->transaction_id,
        ]);
    }

    public function test_purchase_approval_rolls_back_all_changes_when_ledger_posting_fails(): void
    {
        $supplier = $this->supplier();
        $honey = $this->product('Honey', 'Raw Honey');
        $this->post(route('purchases.store'), $this->purchaseData($supplier, [[
            'product_id' => $honey->getKey(),
            'entered_quantity' => '1.000',
            'input_unit' => 'KG',
            'purchase_unit_price' => '10.00',
        ]]))->assertRedirect();
        $invoice = PurchaseInvoice::query()->sole();

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_reject_supplier_purchase_entry()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                RAISE EXCEPTION 'Injected supplier-ledger failure';
            END;
            $function$
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER test_reject_supplier_purchase_entry
            BEFORE INSERT ON supplier_account_entries
            FOR EACH ROW EXECUTE FUNCTION test_reject_supplier_purchase_entry()
        SQL);

        $this->withoutExceptionHandling();
        try {
            $this->post(route('purchases.approve', $invoice));
            $this->fail('Expected the injected supplier-ledger failure.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Injected supplier-ledger failure', $exception->getMessage());
        }

        $this->assertSame('draft', $invoice->businessTransaction()->value('status'));
        $this->assertDatabaseMissing('inventory_movements', [
            'transaction_id' => $invoice->transaction_id,
        ]);
        $this->assertDatabaseMissing('inventory_balances', ['product_id' => $honey->getKey()]);
        $this->assertDatabaseMissing('supplier_account_entries', [
            'source_purchase_transaction_id' => $invoice->transaction_id,
        ]);
    }

    public function test_zero_total_purchase_cannot_be_approved_and_has_no_partial_effects(): void
    {
        $supplier = $this->supplier();
        $ghee = $this->product('Ghee', 'Ghee Can');
        $this->post(route('purchases.store'), $this->purchaseData($supplier, [[
            'product_id' => $ghee->getKey(),
            'entered_quantity' => '1.000',
            'input_unit' => 'CAN',
            'purchase_unit_price' => '0.00',
        ]]))->assertRedirect();
        $invoice = PurchaseInvoice::query()->where('supplier_id', $supplier->getKey())->sole();

        $this->from(route('purchases.show', $invoice))
            ->post(route('purchases.approve', $invoice))
            ->assertSessionHasErrors('invoice_total');

        $this->assertSame('draft', $invoice->businessTransaction()->value('status'));
        $this->assertDatabaseMissing('inventory_movements', ['transaction_id' => $invoice->transaction_id]);
        $this->assertDatabaseMissing('supplier_account_entries', [
            'source_purchase_transaction_id' => $invoice->transaction_id,
        ]);
        $this->assertDatabaseMissing('inventory_balances', ['product_id' => $ghee->getKey()]);
    }

    public function test_category_input_units_are_validated_and_sales_role_cannot_manage_purchases(): void
    {
        $supplier = $this->supplier();
        $ghee = $this->product('Ghee', 'Ghee Can');
        $this->from(route('purchases.create'))
            ->post(route('purchases.store'), $this->purchaseData($supplier, [[
                'product_id' => $ghee->getKey(),
                'entered_quantity' => '1',
                'input_unit' => 'GALLON',
                'purchase_unit_price' => '2.00',
            ]]))
            ->assertSessionHasErrors('lines.0.input_unit');

        $salesUser = $this->createUserWithRole('Sales User');
        $this->actingAs($salesUser)->post(route('purchases.store'))->assertForbidden();
    }

    private function supplier(): Supplier
    {
        $type = SupplierType::query()->where('name', 'Approved Beekeeper')->firstOrFail();

        return Supplier::query()->create([
            'supplier_type_id' => $type->getKey(),
            'name' => 'Purchase Supplier',
            'is_active' => true,
        ]);
    }

    private function product(string $categoryName, string $name): Product
    {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        return Product::query()->create([
            'name' => $name,
            'category_id' => $category->getKey(),
            'base_unit' => match ($categoryName) {
                'Ghee' => BaseInventoryUnit::Can,
                default => BaseInventoryUnit::Kg,
            },
            'selling_price' => '10.00',
            'is_active' => true,
        ]);
    }

    private function purchaseData(Supplier $supplier, array $lines): array
    {
        return [
            'supplier_id' => $supplier->getKey(),
            'supplier_invoice_number' => 'SUP-HONEY-001',
            'document_date' => '2026-11-07',
            'notes' => '',
            'lines' => $lines,
        ];
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::query()->create([
            'username' => 'purchase-'.strtolower(str_replace(' ', '-', $roleName)),
            'display_name' => $roleName,
            'password_hash' => Hash::make('test-password'),
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
