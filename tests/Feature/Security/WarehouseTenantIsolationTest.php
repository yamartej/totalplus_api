<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        Company $company,
        array $permissionNames
    ): User {
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $role = Roles::create([
            'name' => 'Warehouse Tenant Test ' . uniqid(),
            'description' => 'Warehouse tenant isolation test role',
        ]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'Warehouse tenant test permission']
            );

            $role->permissions()
                ->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    private function warehouse(
        Company $company,
        string $name
    ): Warehouse {
        return Warehouse::create([
            'company_id' => $company->id,
            'name' => $name,
            'description' => 'Test warehouse',
            'address' => 'Test address',
        ]);
    }

    public function test_company_user_only_lists_own_warehouses(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, [
            'warehouses.view',
        ]);

        $this->warehouse($companyA, 'Warehouse A');
        $this->warehouse($companyB, 'Warehouse B');

        Sanctum::actingAs($user);

        $this->getJson('/api/warehouses')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Warehouse A'])
            ->assertJsonMissing(['name' => 'Warehouse B']);
    }

    public function test_company_user_cannot_spoof_company_on_create(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, [
            'warehouses.create',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/warehouses', [
            'company_id' => $companyB->id,
            'name' => 'Cross Tenant Warehouse',
            'description' => 'Forbidden',
            'address' => 'Other company',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('warehouses', [
            'name' => 'Cross Tenant Warehouse',
        ]);
    }

    public function test_company_user_cannot_update_other_company_warehouse(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, [
            'warehouses.update',
        ]);

        $foreignWarehouse = $this->warehouse(
            $companyB,
            'Foreign Warehouse'
        );

        Sanctum::actingAs($user);

        $this->putJson(
            '/api/warehouses/' . $foreignWarehouse->id,
            [
                'name' => 'Tampered',
                'description' => 'Tampered',
                'address' => 'Tampered',
            ]
        )->assertStatus(404);

        $this->assertSame(
            'Foreign Warehouse',
            $foreignWarehouse->fresh()->name
        );
    }

    public function test_company_user_cannot_delete_other_company_warehouse(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, [
            'warehouses.delete',
        ]);

        $foreignWarehouse = $this->warehouse(
            $companyB,
            'Foreign Warehouse'
        );

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/warehouses/' . $foreignWarehouse->id
        )->assertStatus(404);

        $this->assertDatabaseHas('warehouses', [
            'id' => $foreignWarehouse->id,
        ]);
    }

    public function test_company_route_rejects_cross_company_id(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, [
            'warehouses.view',
        ]);

        $this->warehouse($companyB, 'Warehouse B');

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/warehouses/get-by-company/' . $companyB->id
        )->assertStatus(403);
    }

    public function test_global_user_requires_explicit_cross_company_permission(): void
    {
        $companyA = Company::create(['name' => 'Company A']);

        $user = User::factory()->create([
            'company_id' => null,
        ]);

        $role = Roles::create([
            'name' => 'Global Warehouse No Tenant Permission',
            'description' => 'Test role',
        ]);

        $view = Permission::create([
            'name' => 'warehouses.view',
            'description' => 'View warehouses',
        ]);

        $role->permissions()->attach($view->id);
        $user->roles()->attach($role->id);

        $this->warehouse($companyA, 'Warehouse A');

        Sanctum::actingAs($user);

        $this->getJson('/api/warehouses')
            ->assertStatus(403);
    }
}
