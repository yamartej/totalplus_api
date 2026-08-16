<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(Company $company, array $permissionNames): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);

        $role = Roles::create([
            'name' => 'Tenant Test ' . uniqid(),
            'description' => 'Tenant isolation test role',
        ]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'Tenant test permission']
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    private function customer(Company $company, int $clientId, string $name): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'client_id' => $clientId,
            'name' => $name,
            'address' => 'Test address',
            'phone' => '555-0100',
        ]);
    }

    public function test_company_user_only_lists_own_customers(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, ['customers.view']);

        $this->customer($companyA, 1001, 'Customer A');
        $this->customer($companyB, 2001, 'Customer B');

        Sanctum::actingAs($user);

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Customer A'])
            ->assertJsonMissing(['name' => 'Customer B']);
    }

    public function test_company_user_cannot_spoof_company_on_create(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, ['customers.create']);

        Sanctum::actingAs($user);

        $this->postJson('/api/customers', [
            'company_id' => $companyB->id,
            'client_id' => 2999,
            'name' => 'Cross Tenant Customer',
            'address' => 'Other company',
            'phone' => '555-9999',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('customers', ['client_id' => 2999]);
    }

    public function test_company_user_cannot_update_other_company_customer(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, ['customers.update']);
        $foreignCustomer = $this->customer($companyB, 2002, 'Foreign Customer');

        Sanctum::actingAs($user);

        $this->putJson('/api/customers/' . $foreignCustomer->id, [
            'client_id' => 2002,
            'name' => 'Tampered',
            'address' => 'Tampered',
            'phone' => '555-2222',
        ])->assertStatus(404);

        $this->assertSame('Foreign Customer', $foreignCustomer->fresh()->name);
    }

    public function test_company_user_cannot_delete_other_company_customer(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, ['customers.delete']);
        $foreignCustomer = $this->customer($companyB, 2003, 'Foreign Customer');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/customers/' . $foreignCustomer->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('customers', ['id' => $foreignCustomer->id]);
    }

    public function test_company_route_rejects_cross_company_id(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->makeUser($companyA, ['customers.view']);

        $this->customer($companyB, 2004, 'Customer B');

        Sanctum::actingAs($user);

        $this->getJson('/api/customers/get-customers-by-company/' . $companyB->id)
            ->assertStatus(403);
    }

    public function test_global_user_requires_explicit_cross_company_permission(): void
    {
        $companyA = Company::create(['name' => 'Company A']);

        $user = User::factory()->create(['company_id' => null]);

        $role = Roles::create([
            'name' => 'Global No Tenant Permission',
            'description' => 'Test role',
        ]);

        $view = Permission::create([
            'name' => 'customers.view',
            'description' => 'View customers',
        ]);

        $role->permissions()->attach($view->id);
        $user->roles()->attach($role->id);

        $this->customer($companyA, 1005, 'Customer A');

        Sanctum::actingAs($user);

        $this->getJson('/api/customers')->assertStatus(403);
    }
}

