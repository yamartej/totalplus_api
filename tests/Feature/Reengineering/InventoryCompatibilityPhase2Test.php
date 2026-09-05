<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryCompatibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCompatibilityPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function grantProductViewPermission(User $user): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 2E Inventory Compatibility'],
            ['description' => 'Test-only Phase 2E role']
        );

        $permission = Permission::firstOrCreate(
            ['name' => 'products.view'],
            ['description' => 'Phase 2E product view permission']
        );

        $role->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);
    }

    private function authHeaders(User $user): array
    {
        $this->grantProductViewPermission($user);

        return [
            'Authorization' => 'Bearer ' .
                $user
                    ->createToken('phase-2e-inventory-test')
                    ->plainTextToken,
        ];
    }

    private function context(): array
    {
        $company = Company::create([
            'name' => 'Phase 2E Company',
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Warehouse A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Warehouse B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'quantity' => 50,
        ]);

        $product->company_id = $company->id;
        $product->save();

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        return compact(
            'company',
            'warehouseA',
            'warehouseB',
            'product',
            'user'
        );
    }

    public function test_canonical_stock_is_sum_of_inventory_balances(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $service = app(InventoryCompatibilityService::class);

        $this->assertSame(
            30,
            $service->totalOnHand($ctx['product'])
        );
    }

    public function test_unallocated_quantity_does_not_mutate_legacy_quantity(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 12,
        ]);

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 8,
        ]);

        $service = app(InventoryCompatibilityService::class);

        $this->assertSame(
            20,
            $service->totalOnHand($ctx['product'])
        );

        $this->assertSame(
            30,
            $service->unallocatedQuantity($ctx['product'])
        );

        $this->assertSame(
            50,
            (int) $ctx['product']->fresh()->quantity
        );
    }

    public function test_product_warehouse_quantity_is_not_a_stock_source(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        DB::table('product_warehouse')->insert([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(InventoryCompatibilityService::class);

        $this->assertSame(
            10,
            $service->totalOnHand($ctx['product'])
        );

        $this->assertSame(
            40,
            $service->unallocatedQuantity($ctx['product'])
        );
    }

    public function test_available_products_exposes_multi_warehouse_compatibility_contract(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['user']))
            ->getJson('/api/products/available');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $ctx['product']->id,
            'quantity' => 20,
            'legacy_quantity' => 50,
            'inventory_total_quantity' => 30,
            'unallocated_quantity' => 20,
        ]);

        $response->assertJsonFragment([
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $response->assertJsonFragment([
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $this->assertSame(
            50,
            (int) $ctx['product']->fresh()->quantity
        );
    }
}
