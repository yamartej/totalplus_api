<?php

namespace Tests\Feature\Reengineering;

use App\Http\Kernel;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class Phase3BearerCsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_nextauth_bearer_api_does_not_run_stateful_session_csrf_globally(): void
    {
        $kernel = app(Kernel::class);
        $this->assertNotContains(
            EnsureFrontendRequestsAreStateful::class,
            $kernel->getMiddleware()
        );
        $this->assertNotContains(
            EnsureFrontendRequestsAreStateful::class,
            $kernel->getMiddlewareGroups()['api']
        );

        // Normal Laravel web forms must remain CSRF protected.
        $this->assertContains(
            \App\Http\Middleware\VerifyCsrfToken::class,
            $kernel->getMiddlewareGroups()['web']
        );
    }

    public function test_supplier_can_be_created_from_localhost_origin_with_sanctum_auth(): void
    {
        $company = Company::create(['name' => 'Browser API company']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $role = Roles::create(['name' => 'Browser API role']);
        $permission = Permission::firstOrCreate(
            ['name' => 'suppliers.create'],
            ['description' => 'Create supplier']
        );
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $this->actingAs($user, 'sanctum');

        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/pages/purchase-orders',
        ])->postJson('/api/suppliers', [
            'name' => 'Phase 3 CSRF regression test',
            'company_id' => $company->id,
        ])->assertCreated();

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Phase 3 CSRF regression test',
            'company_id' => $company->id,
        ]);
    }

    public function test_unauthenticated_browser_origin_does_not_bypass_api_auth(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/pages/purchase-orders',
        ])->postJson('/api/suppliers', [
            'name' => 'Unauthorized supplier',
            'company_id' => 123,
        ])->assertUnauthorized();

        $this->assertSame(0, Supplier::count());
    }
}
