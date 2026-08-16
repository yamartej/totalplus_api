<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBaselineTest extends TestCase
{
    use RefreshDatabase;

    private function grantApiPermissions(
        User $user,
        array $permissionNames
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 0 Inventory Baseline'],
            ['description' => 'Inventory characterization/regression role']
        );

        $permissionIds = collect($permissionNames)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Inventory characterization permission']
                )->id;
            })
            ->all();

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function authHeaders(User $user): array
    {
        $this->grantApiPermissions($user, [
            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
        ]);

        return [
            'Authorization' => 'Bearer ' .
                $user->createToken('inventory-regression-test')->plainTextToken,
        ];
    }

    private function companyAndWarehouses(): array
    {
        $company = Company::create([
            'name' => 'Inventory Regression Company',
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Almacén A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Almacén B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        return compact('company', 'warehouseA', 'warehouseB');
    }

    public function test_inventory_update_keeps_absolute_balance_contract(): void
    {
        $ctx = $this->companyAndWarehouses();

        $user = User::factory()->create([
            'company_id' => $ctx['company']->id,
        ]);

        $product = Product::factory()->create();
        $product->company_id = $ctx['company']->id;
        $product->save();

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->putJson('/api/inventory/' . $inventory->id, [
                'quantity' => 3,
            ]);

        $response->assertStatus(200);
        $this->assertSame(3, (int) $inventory->fresh()->quantity);
    }

    /**
     * Phase 0 documented a defect here: the controller used to search
     * only by product_id and update the first warehouse balance.
     *
     * Phase 2 turns that characterization into a regression assertion:
     * the requested warehouse must be the only balance modified.
     */
    public function test_inventory_addition_is_scoped_to_requested_warehouse(): void
    {
        $ctx = $this->companyAndWarehouses();

        $user = User::factory()->create([
            'company_id' => $ctx['company']->id,
        ]);

        $product = Product::factory()->create();
        $product->company_id = $ctx['company']->id;
        $product->save();

        $inventoryA = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson('/api/inventory/saveInventoryProducts', [
                'warehouse_id' => $ctx['warehouseB']->id,
                'new_product_ids' => [],
                'product_ids' => [
                    ['id' => $product->id, 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertSame(10, (int) $inventoryA->fresh()->quantity);
        $this->assertSame(25, (int) $inventoryB->fresh()->quantity);
    }
}
