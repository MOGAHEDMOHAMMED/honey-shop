<?php

namespace Tests\Feature;

use App\Models\BusinessTransaction;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = $this->createUserWithRole('Administrator');
        $this->actingAs($this->administrator);
    }

    public function test_supplier_can_be_created_listed_searched_and_updated(): void
    {
        $type = $this->supplierType();
        $this->post(route('suppliers.store'), $this->validSupplierData($type))
            ->assertRedirect();

        $supplier = Supplier::query()->where('name', 'Wadi Beekeeper')->firstOrFail();
        $this->assertSame($type->getKey(), $supplier->supplier_type_id);
        $this->assertTrue($supplier->is_active);

        $this->get(route('suppliers.index', ['search' => 'Wadi']))
            ->assertOk()
            ->assertSee('Wadi Beekeeper')
            ->assertSee(__('catalog.supplier_types.Approved Beekeeper'));
        $this->get(route('suppliers.index', ['search' => 'Missing Supplier']))
            ->assertOk()
            ->assertSee(__('catalog.messages.no_suppliers'));

        $this->put(route('suppliers.update', $supplier), $this->validSupplierData($type, [
            'name' => 'Wadi Beekeeper Updated',
            'is_active' => '0',
        ]))->assertRedirect();

        $this->assertSame('Wadi Beekeeper Updated', $supplier->fresh()->name);
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertSame($type->getKey(), $supplier->fresh()->supplier_type_id);
    }

    public function test_duplicate_supplier_names_of_the_same_type_are_rejected(): void
    {
        $type = $this->supplierType();
        $this->post(route('suppliers.store'), $this->validSupplierData($type))->assertRedirect();
        $data = $this->validSupplierData($type, ['name' => '  wadi beekeeper  ']);

        $this->from(route('suppliers.create'))
            ->post(route('suppliers.store'), $data)
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Supplier::query()->where('supplier_type_id', $type->getKey())->count());
    }

    public function test_supplier_type_cannot_change_after_ledger_history_exists(): void
    {
        $type = $this->supplierType();
        $supplier = Supplier::query()->create($this->validSupplierData($type));
        $otherType = SupplierType::query()->where('name', 'Honey Trader')->firstOrFail();

        DB::table('supplier_account_entries')->insert([
            'transaction_id' => $this->purchaseTransactionId($supplier),
            'supplier_id' => $supplier->getKey(),
            'currency_code' => 'YER',
            'debit' => '0.00',
            'credit' => '12.00',
        ]);

        $this->from(route('suppliers.edit', $supplier))
            ->put(route('suppliers.update', $supplier), $this->validSupplierData($otherType))
            ->assertSessionHasErrors('supplier_type_id');

        $this->assertSame($type->getKey(), $supplier->fresh()->supplier_type_id);
    }

    public function test_supplier_with_purchase_history_has_no_hard_delete_operation(): void
    {
        $type = $this->supplierType();
        $supplier = Supplier::query()->create($this->validSupplierData($type));
        $transactionId = $this->purchaseTransactionId($supplier);

        DB::table('purchase_invoices')->insert([
            'transaction_id' => $transactionId,
            'invoice_number' => 'PUR-TEST-REFERENCE',
            'supplier_invoice_number' => 'SUP-ORIGINAL-1',
            'supplier_id' => $supplier->getKey(),
            'currency_code' => 'YER',
            'invoice_total' => '10.00',
        ]);

        $this->delete(route('suppliers.show', $supplier))->assertStatus(405);
        $this->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Wadi Beekeeper')
            ->assertSee('PUR-TEST-REFERENCE')
            ->assertSee('SUP-ORIGINAL-1');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->getKey()]);
    }

    public function test_sales_role_cannot_view_supplier_routes(): void
    {
        $salesUser = $this->createUserWithRole('Sales User');
        $this->actingAs($salesUser)
            ->get(route('suppliers.index'))
            ->assertForbidden();
    }

    private function supplierType(): SupplierType
    {
        return SupplierType::query()->where('name', 'Approved Beekeeper')->firstOrFail();
    }

    private function validSupplierData(SupplierType $type, array $overrides = []): array
    {
        return array_replace([
            'supplier_type_id' => $type->getKey(),
            'name' => 'Wadi Beekeeper',
            'phone' => '777123456',
            'address' => 'Sana’a',
            'notes' => '',
            'is_active' => '1',
        ], $overrides);
    }

    private function purchaseTransactionId(Supplier $supplier): string
    {
        $transaction = BusinessTransaction::query()->create([
            'transaction_type' => 'purchase',
            'status' => 'draft',
            'occurred_at' => now(),
            'created_by' => $this->administrator->getKey(),
        ]);

        return $transaction->getKey();
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::query()->create([
            'username' => 'supplier-'.strtolower(str_replace(' ', '-', $roleName)),
            'display_name' => $roleName,
            'password_hash' => Hash::make('test-password'),
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
