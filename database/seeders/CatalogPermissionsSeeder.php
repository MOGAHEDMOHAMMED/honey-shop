<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'catalog.categories.view' => 'View product categories',
        'catalog.categories.manage' => 'Activate or deactivate product categories',
        'catalog.products.view' => 'View products',
        'catalog.products.manage' => 'Create and update products',
        'catalog.products.price-history.view' => 'View product price history',
        'suppliers.view' => 'View suppliers',
        'suppliers.manage' => 'Create and update suppliers',
        'opening-inventory.view' => 'View opening inventory',
        'opening-inventory.manage' => 'Create and approve opening inventory',
        'purchases.view' => 'View purchase invoices',
        'purchases.manage' => 'Create and edit draft purchases',
        'purchases.approve' => 'Approve purchase invoices',
        'customers.view' => 'View customers and balances',
        'customers.manage' => 'Create and update customers',
        'sales.view' => 'View sales invoices',
        'sales.manage' => 'Create and edit draft sales',
        'sales.approve' => 'Approve sales and post inventory',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = [];
            foreach (self::PERMISSIONS as $code => $description) {
                $permissions[] = ['code' => $code, 'description' => $description];
            }
            DB::table('permissions')->upsert($permissions, ['code'], ['description']);

            $roles = DB::table('roles')
                ->whereIn('name', ['Administrator', 'Inventory and Purchasing User', 'Sales User'])
                ->pluck('id', 'name');

            foreach (['Administrator', 'Inventory and Purchasing User', 'Sales User'] as $name) {
                if (! $roles->has($name)) {
                    throw new RuntimeException("Required role not found: {$name}");
                }
            }

            $allPermissions = array_keys(self::PERMISSIONS);
            $salesPermissions = [
                'catalog.categories.view',
                'catalog.products.view',
                'catalog.products.price-history.view',
                'customers.view',
                'customers.manage',
                'sales.view',
                'sales.manage',
                'sales.approve',
            ];
            $assignments = [];

            $inventoryPermissions = array_values(array_diff($allPermissions, [
                'customers.view',
                'customers.manage',
                'sales.view',
                'sales.manage',
                'sales.approve',
            ]));

            DB::table('role_permissions')
                ->where('role_id', $roles['Inventory and Purchasing User'])
                ->whereIn('permission_code', [
                    'customers.view',
                    'customers.manage',
                    'sales.view',
                    'sales.manage',
                    'sales.approve',
                ])
                ->delete();

            foreach ([
                'Administrator' => $allPermissions,
                'Inventory and Purchasing User' => $inventoryPermissions,
            ] as $roleName => $rolePermissions) {
                foreach ($rolePermissions as $permission) {
                    $assignments[] = [
                        'role_id' => $roles[$roleName],
                        'permission_code' => $permission,
                    ];
                }
            }

            foreach ($salesPermissions as $permission) {
                $assignments[] = [
                    'role_id' => $roles['Sales User'],
                    'permission_code' => $permission,
                ];
            }

            DB::table('role_permissions')->insertOrIgnore($assignments);
        });
    }
}
