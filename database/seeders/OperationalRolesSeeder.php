<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Roles;
use Illuminate\Database\Seeder;

class OperationalRolesSeeder extends Seeder
{
    public function run()
    {
        $role = Roles::updateOrCreate(
            ['name' => 'Administrator'],
            ['description' => 'Full system access']
        );

        $role->permissions()->sync(
            Permission::query()->pluck('id')->all()
        );

        $role = Roles::updateOrCreate(
            ['name' => 'Manager'],
            ['description' => 'Operational management without security administration or destructive deletes']
        );

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('name', [
                'users.view',
                'roles.view',
                'permissions.view',
                'products.view',
                'products.create',
                'products.update',
                'inventory.view',
                'inventory.create',
                'inventory.update',
                'categories.view',
                'categories.manage',
                'customers.view',
                'customers.create',
                'customers.update',
                'warehouses.view',
                'warehouses.create',
                'warehouses.update',
                'pops.view',
                'pops.create',
                'pops.update',
                'sales.view',
                'sales.create',
                'sales.update',
                'reports.view',
                'suppliers.view',
                'suppliers.create',
                'suppliers.update',
                'purchaseorders.view',
                'purchaseorders.create',
                'purchaseorders.update',
                'purchaseorderproducts.view',
                'purchaseorderproducts.create',
                'purchaseorderproducts.update',
                'cashregisters.view',
                'cashregisters.create',
                'cashregisters.update',
                'batches.view',
                'batches.create',
                'batches.update',
                'costs.view',
                'costs.create',
                'costs.update',
                'credits.view',
                'credits.create',
                'credits.update'
                ])
                ->pluck('id')
                ->all()
        );

        $role = Roles::updateOrCreate(
            ['name' => 'Sales'],
            ['description' => 'Sales, customers, POS, cash register and credit operations']
        );

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('name', [
                'products.view',
                'inventory.view',
                'customers.view',
                'customers.create',
                'customers.update',
                'pops.view',
                'sales.view',
                'sales.create',
                'sales.update',
                'cashregisters.view',
                'cashregisters.create',
                'cashregisters.update',
                'credits.view',
                'credits.create',
                'credits.update'
                ])
                ->pluck('id')
                ->all()
        );

        $role = Roles::updateOrCreate(
            ['name' => 'Inventory'],
            ['description' => 'Products, stock, warehouses, batches and costing operations']
        );

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('name', [
                'products.view',
                'products.create',
                'products.update',
                'inventory.view',
                'inventory.create',
                'inventory.update',
                'categories.view',
                'categories.manage',
                'warehouses.view',
                'warehouses.create',
                'warehouses.update',
                'batches.view',
                'batches.create',
                'batches.update',
                'costs.view',
                'costs.create',
                'costs.update',
                'suppliers.view',
                'purchaseorderproducts.view'
                ])
                ->pluck('id')
                ->all()
        );

        $role = Roles::updateOrCreate(
            ['name' => 'Purchasing'],
            ['description' => 'Suppliers, purchase orders, receiving support and costing']
        );

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('name', [
                'products.view',
                'inventory.view',
                'warehouses.view',
                'suppliers.view',
                'suppliers.create',
                'suppliers.update',
                'purchaseorders.view',
                'purchaseorders.create',
                'purchaseorders.update',
                'purchaseorderproducts.view',
                'purchaseorderproducts.create',
                'purchaseorderproducts.update',
                'batches.view',
                'batches.create',
                'batches.update',
                'costs.view',
                'costs.create',
                'costs.update'
                ])
                ->pluck('id')
                ->all()
        );

        $role = Roles::updateOrCreate(
            ['name' => 'Auditor'],
            ['description' => 'Read-only access to operational and security information']
        );

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('name', [
                'users.view',
                'roles.view',
                'permissions.view',
                'products.view',
                'inventory.view',
                'categories.view',
                'customers.view',
                'warehouses.view',
                'pops.view',
                'sales.view',
                'reports.view',
                'suppliers.view',
                'purchaseorders.view',
                'purchaseorderproducts.view',
                'cashregisters.view',
                'batches.view',
                'costs.view',
                'credits.view'
                ])
                ->pluck('id')
                ->all()
        );

    }
}
