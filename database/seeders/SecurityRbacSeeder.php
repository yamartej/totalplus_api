<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Roles;
use Illuminate\Database\Seeder;

class SecurityRbacSeeder extends Seeder
{
    public function run()
    {
        $administrator = Roles::updateOrCreate(
            ['name' => 'Administrator'],
            ['description' => 'Full system access']
        );

        $definitions = [
            ['name' => 'users.view', 'description' => 'users view'],
            ['name' => 'users.create', 'description' => 'users create'],
            ['name' => 'users.update', 'description' => 'users update'],
            ['name' => 'users.delete', 'description' => 'users delete'],
            ['name' => 'roles.view', 'description' => 'roles view'],
            ['name' => 'roles.manage', 'description' => 'roles manage'],
            ['name' => 'permissions.view', 'description' => 'permissions view'],
            ['name' => 'permissions.manage', 'description' => 'permissions manage'],
            ['name' => 'products.view', 'description' => 'products view'],
            ['name' => 'products.create', 'description' => 'products create'],
            ['name' => 'products.update', 'description' => 'products update'],
            ['name' => 'products.delete', 'description' => 'products delete'],
            ['name' => 'inventory.view', 'description' => 'inventory view'],
            ['name' => 'inventory.create', 'description' => 'inventory create'],
            ['name' => 'inventory.update', 'description' => 'inventory update'],
            ['name' => 'inventory.delete', 'description' => 'inventory delete'],
            ['name' => 'categories.view', 'description' => 'categories view'],
            ['name' => 'categories.manage', 'description' => 'categories manage'],
            ['name' => 'customers.view', 'description' => 'customers view'],
            ['name' => 'customers.create', 'description' => 'customers create'],
            ['name' => 'customers.update', 'description' => 'customers update'],
            ['name' => 'customers.delete', 'description' => 'customers delete'],
            ['name' => 'warehouses.view', 'description' => 'warehouses view'],
            ['name' => 'warehouses.create', 'description' => 'warehouses create'],
            ['name' => 'warehouses.update', 'description' => 'warehouses update'],
            ['name' => 'warehouses.delete', 'description' => 'warehouses delete'],
            ['name' => 'pops.view', 'description' => 'pops view'],
            ['name' => 'pops.create', 'description' => 'pops create'],
            ['name' => 'pops.update', 'description' => 'pops update'],
            ['name' => 'pops.delete', 'description' => 'pops delete'],
            ['name' => 'sales.view', 'description' => 'sales view'],
            ['name' => 'sales.create', 'description' => 'sales create'],
            ['name' => 'sales.update', 'description' => 'sales update'],
            ['name' => 'sales.delete', 'description' => 'sales delete'],
            ['name' => 'reports.view', 'description' => 'reports view'],
            ['name' => 'suppliers.view', 'description' => 'suppliers view'],
            ['name' => 'suppliers.create', 'description' => 'suppliers create'],
            ['name' => 'suppliers.update', 'description' => 'suppliers update'],
            ['name' => 'suppliers.delete', 'description' => 'suppliers delete'],
            ['name' => 'purchaseorders.view', 'description' => 'purchaseorders view'],
            ['name' => 'purchaseorders.create', 'description' => 'purchaseorders create'],
            ['name' => 'purchaseorders.update', 'description' => 'purchaseorders update'],
            ['name' => 'purchaseorders.delete', 'description' => 'purchaseorders delete'],
            ['name' => 'purchaseorderproducts.view', 'description' => 'purchaseorderproducts view'],
            ['name' => 'purchaseorderproducts.create', 'description' => 'purchaseorderproducts create'],
            ['name' => 'purchaseorderproducts.update', 'description' => 'purchaseorderproducts update'],
            ['name' => 'purchaseorderproducts.delete', 'description' => 'purchaseorderproducts delete'],
            ['name' => 'cashregisters.view', 'description' => 'cashregisters view'],
            ['name' => 'cashregisters.create', 'description' => 'cashregisters create'],
            ['name' => 'cashregisters.update', 'description' => 'cashregisters update'],
            ['name' => 'cashregisters.delete', 'description' => 'cashregisters delete'],
            ['name' => 'batches.view', 'description' => 'batches view'],
            ['name' => 'batches.create', 'description' => 'batches create'],
            ['name' => 'batches.update', 'description' => 'batches update'],
            ['name' => 'batches.delete', 'description' => 'batches delete'],
            ['name' => 'costs.view', 'description' => 'costs view'],
            ['name' => 'costs.create', 'description' => 'costs create'],
            ['name' => 'costs.update', 'description' => 'costs update'],
            ['name' => 'costs.delete', 'description' => 'costs delete'],
            ['name' => 'credits.view', 'description' => 'credits view'],
            ['name' => 'credits.create', 'description' => 'credits create'],
            ['name' => 'credits.update', 'description' => 'credits update'],
            ['name' => 'credits.delete', 'description' => 'credits delete']
        ];

        $permissionIds = [];

        foreach ($definitions as $definition) {
            $permission = Permission::updateOrCreate(
                ['name' => $definition['name']],
                ['description' => $definition['description']]
            );

            $permissionIds[] = $permission->id;
        }

        // Idempotent: Administrator always receives the complete canonical set.
        $administrator->permissions()->sync($permissionIds);
    }
}
