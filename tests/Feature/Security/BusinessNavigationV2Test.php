<?php

namespace Tests\Feature\Security;

use App\Models\Menu;
use App\Models\RolePermission;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessNavigationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_uses_existing_frontend_routes_and_business_groups(): void
    {
        $groups = [
            '/menu/sales' => '/pages/sales',
            '/menu/purchasing' => '/pages/purchase-orders',
            '/menu/inventory' => '/pages/kardex',
            '/menu/catalog' => '/pages/products',
            '/menu/credits' => '/pages/payment-details',
            '/menu/reports' => '/pages/sales-report',
            '/menu/administration' => '/pages/permissions',
        ];

        foreach ($groups as $groupUrl => $childUrl) {
            $parent = Menu::where('url', $groupUrl)->firstOrFail();
            $child = Menu::where('url', $childUrl)->firstOrFail();

            $this->assertSame($parent->id, (int) $child->parent_id);
        }

        $this->assertFalse(Menu::where('url', '/pages/cashregisters')->exists());
        $this->assertFalse(Menu::where('url', '/pages/roles')->exists());
    }

    public function test_reorganization_keeps_menu_id_and_existing_role_grants(): void
    {
        $product = Menu::where('url', '/pages/products')->firstOrFail();
        $oldId = $product->id;
        $inventory = Menu::where('url', '/menu/inventory')->firstOrFail();
        $product->update(['parent_id' => $inventory->id]);

        $role = Roles::create(['name' => 'Catalog Viewer']);
        $grant = RolePermission::create([
            'role_id' => $role->id,
            'menu_id' => $oldId,
            'can_access' => true,
        ]);

        $migration = require database_path(
            'migrations/2026_11_01_000200_align_business_navigation.php'
        );
        $migration->up();
        $migration->up();

        $catalog = Menu::where('url', '/menu/catalog')->firstOrFail();
        $moved = Menu::where('url', '/pages/products')->firstOrFail();

        $this->assertSame($oldId, $moved->id);
        $this->assertSame($catalog->id, (int) $moved->parent_id);
        $this->assertSame(1, Menu::where('url', '/pages/products')->count());
        $this->assertSame(1, RolePermission::whereKey($grant->id)
            ->where('menu_id', $oldId)
            ->where('can_access', true)->count());
        $this->assertSame(1, RolePermission::where('role_id', $role->id)->count());
    }

    public function test_authorized_leaf_includes_its_heading_but_not_siblings(): void
    {
        $user = User::factory()->create();
        $role = Roles::create(['name' => 'Sales Only']);
        $user->roles()->attach($role->id);

        $sale = Menu::where('url', '/pages/sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id,
            'menu_id' => $sale->id,
            'can_access' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/menu_items')
            ->assertOk()
            ->assertJsonFragment(['url' => '/menu/sales'])
            ->assertJsonFragment(['url' => '/pages/sales'])
            ->assertJsonMissing(['url' => '/pages/pops'])
            ->assertJsonMissing(['url' => '/menu/catalog']);
    }

    public function test_heading_without_accessible_children_is_not_a_dead_link(): void
    {
        $user = User::factory()->create();
        $role = Roles::create(['name' => 'Headings Only']);
        $user->roles()->attach($role->id);

        $heading = Menu::where('url', '/menu/inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id,
            'menu_id' => $heading->id,
            'can_access' => true,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/menu_items')->assertOk()->assertExactJson([]);
    }
}
