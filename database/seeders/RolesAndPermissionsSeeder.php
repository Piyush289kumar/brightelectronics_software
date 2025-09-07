<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions (customize as per your modules)
        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $customer = Role::firstOrCreate(['name' => 'customer']);
        $supplier = Role::firstOrCreate(['name' => 'supplier']);

        // Assign all permissions to admin
        $admin->syncPermissions(Permission::all());

        // Assign some permissions to staff (customize as needed)
        $staff->syncPermissions([
            'users.view',
            'products.view',
            'orders.view',
            'orders.create',
        ]);

        // Assign limited permissions to customer
        $customer->syncPermissions([
            'orders.view',
            'orders.create',
        ]);

        // Assign permissions to supplier
        $supplier->syncPermissions([
            'products.view',
            'products.edit',
        ]);
    }
}
