<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use Database\Seeders\SecurityRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiPermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_without_sales_permission_is_forbidden(): void
    {
        $company = Company::create([
            'name' => 'RBAC Test Company',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $role = Roles::create([
            'name' => 'No Sales Access',
            'description' => 'Test role',
        ]);

        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/sales')
            ->assertStatus(403)
            ->assertJsonPath(
                'required_permission',
                'sales.view'
            );
    }

    public function test_role_with_sales_view_permission_can_access_sales_list(): void
    {
        $company = Company::create([
            'name' => 'Sales Viewer Company',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $role = Roles::create([
            'name' => 'Sales Viewer',
            'description' => 'Test role',
        ]);

        $permission = Permission::create([
            'name' => 'sales.view',
            'description' => 'View sales',
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/sales')->assertOk();
    }

    public function test_administrator_seeder_assigns_all_canonical_permissions(): void
    {
        $this->seed(SecurityRbacSeeder::class);

        $administrator = Roles::where(
            'name',
            'Administrator'
        )->firstOrFail();

        $this->assertGreaterThanOrEqual(
            60,
            $administrator->permissions()->count()
        );

        $this->assertTrue(
            $administrator->permissions()
                ->where('name', 'sales.create')
                ->exists()
        );

        $this->assertTrue(
            $administrator->permissions()
                ->where('name', 'users.delete')
                ->exists()
        );
    }

    public function test_sales_route_still_requires_authentication(): void
    {
        $this->getJson('/api/sales')
            ->assertStatus(401);
    }
}
