<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Permission;
use App\Models\PointOfSale;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PointOfSaleTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(Company $company): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $role = Roles::create([
            'name' => 'POS Tenant Test ' . uniqid(),
            'description' => 'POS tenant isolation test role',
        ]);

        foreach ([
            'pops.view',
            'pops.create',
            'pops.update',
            'pops.delete',
        ] as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'POS tenant test permission']
            );

            $role->permissions()
                ->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        $this->assertSame(
            (int) $company->id,
            (int) $user->fresh()->company_id
        );

        return $user->fresh();
    }

    private function pop(
        Company $company,
        string $identifier
    ): PointOfSale {
        $pop = PointOfSale::create([
            'company_id' => $company->id,
            'identifier' => $identifier,
            'ubication' => 'Test location',
            'status' => 'created',
        ]);

        $this->assertSame(
            (int) $company->id,
            (int) $pop->fresh()->company_id
        );

        return $pop->fresh();
    }

    public function test_company_user_only_lists_own_points_of_sale(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA);

        $this->pop($companyA, 'POS-A');
        $this->pop($companyB, 'POS-B');

        Sanctum::actingAs($user);

        $this->getJson('/api/pops')
            ->assertOk()
            ->assertJsonFragment(['identifier' => 'POS-A'])
            ->assertJsonMissing(['identifier' => 'POS-B']);
    }

    public function test_company_user_cannot_spoof_company_on_create(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA);

        Sanctum::actingAs($user);

        $this->postJson('/api/pops', [
            'company_id' => $companyB->id,
            'identifier' => 'CROSS-POS',
            'ubication' => 'Other company',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('point_of_sales', [
            'identifier' => 'CROSS-POS',
        ]);
    }

    public function test_company_user_cannot_update_other_company_point_of_sale(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA);
        $foreignPop = $this->pop($companyB, 'FOREIGN-POS');

        Sanctum::actingAs($user);

        $this->putJson('/api/pops/' . $foreignPop->id, [
            'identifier' => 'TAMPERED',
            'ubication' => 'Tampered',
            'status' => 'created',
        ])->assertStatus(404);

        $this->assertSame(
            'FOREIGN-POS',
            $foreignPop->fresh()->identifier
        );
    }

    public function test_company_user_cannot_delete_other_company_point_of_sale(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA);
        $foreignPop = $this->pop($companyB, 'FOREIGN-POS');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/pops/' . $foreignPop->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('point_of_sales', [
            'id' => $foreignPop->id,
        ]);
    }

    public function test_company_route_rejects_cross_company_id(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA);

        $this->pop($companyB, 'POS-B');

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/pops/by-company/' . $companyB->id
        )->assertStatus(403);
    }

    public function test_point_of_sale_cannot_assign_seller_from_other_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA);

        $foreignSeller = User::factory()->create([
            'company_id' => $companyB->id,
        ]);

        $this->assertSame(
            (int) $companyB->id,
            (int) $foreignSeller->fresh()->company_id
        );

        $pop = $this->pop($companyA, 'POS-A');

        Sanctum::actingAs($user);

        $this->putJson('/api/pops/' . $pop->id, [
            'identifier' => 'POS-A',
            'ubication' => 'Test location',
            'status' => 'created',
            'seller_id' => $foreignSeller->id,
        ])->assertStatus(404);

        $this->assertNull($pop->fresh()->seller_id);
    }

    public function test_global_user_requires_explicit_cross_company_permission(): void
    {
        $companyA = Company::create(['name' => 'Company A']);

        $user = User::factory()->create([
            'company_id' => null,
        ]);

        $role = Roles::create([
            'name' => 'Global POS No Tenant Permission',
            'description' => 'Test role',
        ]);

        foreach ([
            'pops.view',
            'pops.create',
            'pops.update',
            'pops.delete',
        ] as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'POS permission']
            );
            $role->permissions()
                ->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        $this->pop($companyA, 'POS-A');

        Sanctum::actingAs($user);

        $this->getJson('/api/pops')
            ->assertStatus(403);
    }
}
