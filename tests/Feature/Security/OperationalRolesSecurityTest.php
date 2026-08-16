<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Roles;
use Database\Seeders\OperationalRolesSeeder;
use Database\Seeders\SecurityRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalRolesSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SecurityRbacSeeder::class);
        $this->seed(OperationalRolesSeeder::class);
    }

    public function test_administrator_has_all_permissions(): void
    {
        $administrator = Roles::where('name', 'Administrator')->firstOrFail();

        $this->assertSame(
            Permission::count(),
            $administrator->permissions()->count()
        );
    }

    public function test_sales_can_create_sales_but_cannot_manage_users(): void
    {
        $sales = Roles::where('name', 'Sales')->firstOrFail();

        $this->assertTrue(
            $sales->permissions()->where('name', 'sales.create')->exists()
        );

        $this->assertFalse(
            $sales->permissions()->where('name', 'users.create')->exists()
        );
    }

    public function test_inventory_can_update_stock_but_cannot_create_sales(): void
    {
        $inventory = Roles::where('name', 'Inventory')->firstOrFail();

        $this->assertTrue(
            $inventory->permissions()->where('name', 'inventory.update')->exists()
        );

        $this->assertFalse(
            $inventory->permissions()->where('name', 'sales.create')->exists()
        );
    }

    public function test_purchasing_can_manage_purchase_orders_but_not_delete_sales(): void
    {
        $purchasing = Roles::where('name', 'Purchasing')->firstOrFail();

        $this->assertTrue(
            $purchasing->permissions()->where('name', 'purchaseorders.create')->exists()
        );

        $this->assertFalse(
            $purchasing->permissions()->where('name', 'sales.delete')->exists()
        );
    }

    public function test_auditor_is_read_only(): void
    {
        $auditor = Roles::where('name', 'Auditor')->firstOrFail();

        $this->assertTrue(
            $auditor->permissions()->where('name', 'reports.view')->exists()
        );

        $this->assertFalse(
            $auditor->permissions()->where('name', 'sales.create')->exists()
        );

        $this->assertFalse(
            $auditor->permissions()->where('name', 'inventory.update')->exists()
        );
    }

    public function test_manager_has_operational_access_but_not_security_management(): void
    {
        $manager = Roles::where('name', 'Manager')->firstOrFail();

        $this->assertTrue(
            $manager->permissions()->where('name', 'reports.view')->exists()
        );

        $this->assertTrue(
            $manager->permissions()->where('name', 'sales.create')->exists()
        );

        $this->assertFalse(
            $manager->permissions()->where('name', 'permissions.manage')->exists()
        );

        $this->assertFalse(
            $manager->permissions()->where('name', 'users.delete')->exists()
        );
    }
}
