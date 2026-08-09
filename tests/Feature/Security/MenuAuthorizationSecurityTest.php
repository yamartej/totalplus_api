<?php

namespace Tests\Feature\Security;

use App\Models\Menu;
use App\Models\RolePermission;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuAuthorizationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_items_uses_authenticated_users_roles_not_client_role_ids(): void
    {
        $user = User::factory()->create();

        $realRole = Roles::create([
            'name' => 'Seller',
            'description' => 'Real role',
        ]);

        $spoofedRole = Roles::create([
            'name' => 'Administrator',
            'description' => 'Role the client will try to spoof',
        ]);

        $user->roles()->attach($realRole->id);

        $salesMenu = Menu::create([
            'name' => 'Sales',
            'url' => '/pages/sales',
            'parent_id' => null,
            'order' => 1,
            'icon' => 'SalesIcon',
        ]);

        $adminMenu = Menu::create([
            'name' => 'Administration',
            'url' => '/pages/administration',
            'parent_id' => null,
            'order' => 2,
            'icon' => 'SettingsIcon',
        ]);

        RolePermission::create([
            'role_id' => $realRole->id,
            'menu_id' => $salesMenu->id,
            'can_access' => true,
        ]);

        RolePermission::create([
            'role_id' => $spoofedRole->id,
            'menu_id' => $adminMenu->id,
            'can_access' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/menu_items', [
            'role_ids' => [$spoofedRole->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $salesMenu->id,
                'name' => 'Sales',
            ])
            ->assertJsonMissing([
                'id' => $adminMenu->id,
                'name' => 'Administration',
            ]);
    }

    public function test_menu_items_no_longer_requires_role_ids_from_client(): void
    {
        $user = User::factory()->create();

        $role = Roles::create([
            'name' => 'Inventory',
            'description' => 'Inventory role',
        ]);

        $user->roles()->attach($role->id);

        $menu = Menu::create([
            'name' => 'Inventory',
            'url' => '/pages/inventory',
            'parent_id' => null,
            'order' => 1,
            'icon' => 'StockIcon',
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'menu_id' => $menu->id,
            'can_access' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/menu_items')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $menu->id,
                'name' => 'Inventory',
            ]);
    }

    public function test_user_without_roles_receives_empty_menu(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/menu_items')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_menu_items_requires_authentication(): void
    {
        $this->postJson('/api/menu_items')
            ->assertStatus(401);
    }
}