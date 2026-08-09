<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRole(array $permissions): Roles
    {
        $role = Roles::create([
            'name' => 'User Tenant Test ' . uniqid(),
            'description' => 'User tenant isolation test role',
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'User tenant test permission']
            );

            $role->permissions()
                ->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    private function makeUser(
        Company $company,
        array $permissions = []
    ): User {
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        if ($permissions) {
            $role = $this->makeRole($permissions);
            $user->roles()->attach($role->id);
        }

        return $user->fresh();
    }

    private function actor(Company $company): User
    {
        return $this->makeUser($company, [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ]);
    }

    public function test_company_user_only_lists_own_users(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->actor($companyA);
        $ownUser = $this->makeUser($companyA);
        $foreignUser = $this->makeUser($companyB);

        Sanctum::actingAs($actor);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $ownUser->id])
            ->assertJsonMissing(['id' => $foreignUser->id]);
    }

    public function test_company_user_creates_user_in_own_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $actor = $this->actor($companyA);
        $newRole = $this->makeRole([]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new-user@example.test',
            'password' => 'secret123',
            'rol' => [$newRole->id],
        ])
            ->assertCreated()
            ->assertJsonPath('company_id', $companyA->id);

        $this->assertDatabaseHas('users', [
            'email' => 'new-user@example.test',
            'company_id' => $companyA->id,
        ]);
    }

    public function test_company_user_cannot_spoof_company_on_create(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->actor($companyA);
        $newRole = $this->makeRole([]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/users', [
            'name' => 'Cross Tenant User',
            'email' => 'cross-user@example.test',
            'password' => 'secret123',
            'rol' => [$newRole->id],
            'company_id' => $companyB->id,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('users', [
            'email' => 'cross-user@example.test',
        ]);
    }

    public function test_company_user_cannot_view_other_company_user(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->actor($companyA);
        $foreignUser = $this->makeUser($companyB);

        Sanctum::actingAs($actor);

        $this->getJson('/api/users/' . $foreignUser->id)
            ->assertStatus(404);
    }

    public function test_company_user_cannot_update_other_company_user(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->actor($companyA);
        $foreignUser = $this->makeUser($companyB);

        Sanctum::actingAs($actor);

        $this->putJson('/api/users/' . $foreignUser->id, [
            'name' => 'Tampered User',
        ])->assertStatus(404);

        $this->assertNotSame(
            'Tampered User',
            $foreignUser->fresh()->name
        );
    }

    public function test_company_user_cannot_delete_other_company_user(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->actor($companyA);
        $foreignUser = $this->makeUser($companyB);

        Sanctum::actingAs($actor);

        $this->deleteJson('/api/users/' . $foreignUser->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('users', [
            'id' => $foreignUser->id,
        ]);
    }

    public function test_company_route_rejects_cross_company_id(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->actor($companyA);
        $this->makeUser($companyB);

        Sanctum::actingAs($actor);

        $this->getJson(
            '/api/users/getUsersByCompany/' . $companyB->id
        )->assertStatus(403);
    }

    public function test_global_user_requires_explicit_cross_company_permission(): void
    {
        $companyA = Company::create(['name' => 'Company A']);

        $globalUser = User::factory()->create([
            'company_id' => null,
        ]);

        $role = $this->makeRole([
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ]);

        $globalUser->roles()->attach($role->id);
        $this->makeUser($companyA);

        Sanctum::actingAs($globalUser);

        $this->getJson('/api/users')
            ->assertStatus(403);
    }
}
