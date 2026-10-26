<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Authenticate legacy endpoint regression tests with a real tenant and
 * explicit role permissions.  Do not disable middleware in these tests.
 */
trait AuthenticatedCompany
{
    protected Company $qaCompany;
    protected User $qaAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qaCompany = Company::create([
            'name' => 'CI Legacy Contract Company ' . uniqid(),
        ]);

        $this->qaAdmin = $this->makeAuthorizedUser();
        Sanctum::actingAs($this->qaAdmin);
    }

    protected function makeAuthorizedUser(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->qaCompany->id,
        ]);

        $permissions = [
            'products.view', 'products.create', 'products.update', 'products.delete',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'purchaseorders.view', 'purchaseorders.create', 'purchaseorders.update',
            'purchaseorders.delete', 'purchaseorderproducts.view',
            'purchaseorderproducts.create', 'purchaseorderproducts.update',
            'purchaseorderproducts.delete',
            'cashregisters.view', 'cashregisters.create', 'cashregisters.update',
            'cashregisters.delete', 'categories.view', 'categories.manage',
            'roles.view', 'roles.manage', 'sales.view', 'sales.create',
            'sales.update', 'sales.delete', 'reports.view',
        ];

        $role = Roles::create([
            'name' => 'CI Legacy Contract Role ' . uniqid(),
            'description' => 'Authenticated endpoint contract fixtures',
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'Test permission']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
