<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryControllerPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function grantApiPermissions(
        User $user,
        array $permissionNames
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 2 Inventory Controller'],
            ['description' => 'Test-only Phase 2 inventory controller role']
        );

        $permissionIds = collect($permissionNames)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 2 inventory test permission']
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
                $user->createToken('phase-2-inventory-test')->plainTextToken,
        ];
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 2 Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 2 Company B',
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Warehouse A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $companyA->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Warehouse B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $companyA->id,
        ]);

        $otherWarehouse = Warehouse::create([
            'name' => 'Other Company Warehouse',
            'description' => 'Other',
            'address' => 'Other',
            'company_id' => $companyB->id,
        ]);

        $productA = Product::factory()->create();
        $productA->company_id = $companyA->id;
        $productA->save();

        $productB = Product::factory()->create();
        $productB->company_id = $companyB->id;
        $productB->save();

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        return compact(
            'companyA',
            'companyB',
            'warehouseA',
            'warehouseB',
            'otherWarehouse',
            'productA',
            'productB',
            'userA'
        );
    }

    public function test_inventory_index_is_scoped_to_user_company(): void
    {
        $ctx = $this->context();

        $ownInventory = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $otherInventory = Inventory::create([
            'product_id' => $ctx['productB']->id,
            'warehouse_id' => $ctx['otherWarehouse']->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->getJson('/api/inventory');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'id' => $ownInventory->id,
        ]);
        $response->assertJsonMissing([
            'id' => $otherInventory->id,
        ]);
    }

    public function test_save_inventory_products_updates_requested_warehouse_only(): void
    {
        $ctx = $this->context();

        $inventoryA = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->postJson('/api/inventory/saveInventoryProducts', [
                'warehouse_id' => $ctx['warehouseB']->id,
                'new_product_ids' => [],
                'product_ids' => [
                    [
                        'id' => $ctx['productA']->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertSame(10, (int) $inventoryA->fresh()->quantity);
        $this->assertSame(25, (int) $inventoryB->fresh()->quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $ctx['companyA']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'product_id' => $ctx['productA']->id,
            'type' => InventoryMovement::TYPE_RECEIVE,
            'quantity_delta' => 5,
            'balance_before' => 20,
            'balance_after' => 25,
        ]);
    }

    public function test_inventory_update_uses_adjustment_and_preserves_exact_key(): void
    {
        $ctx = $this->context();

        $inventory = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->putJson('/api/inventory/' . $inventory->id, [
                'product_id' => $ctx['productA']->id,
                'warehouse_id' => $ctx['warehouseA']->id,
                'quantity' => 3,
            ]);

        $response->assertStatus(200);
        $this->assertSame(3, (int) $inventory->fresh()->quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $ctx['warehouseA']->id,
            'product_id' => $ctx['productA']->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity_delta' => -7,
            'balance_before' => 10,
            'balance_after' => 3,
        ]);
    }

    public function test_remove_assignment_zeroes_projection_but_keeps_kardex(): void
    {
        $ctx = $this->context();

        $service = app(InventoryService::class);

        $service->receive(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA']->id,
            10
        );

        $inventory = Inventory::query()
            ->where('product_id', $ctx['productA']->id)
            ->where('warehouse_id', $ctx['warehouseA']->id)
            ->firstOrFail();

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->postJson('/api/inventory/removeAssignedInventory', [
                'product_ids' => [
                    ['id' => $ctx['productA']->id],
                ],
            ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 0,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $ctx['companyA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'product_id' => $ctx['productA']->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity_delta' => -10,
            'balance_before' => 10,
            'balance_after' => 0,
        ]);

        $this->assertNull(
            $ctx['productA']->fresh()->inventory
        );
    }

    public function test_remove_assignment_requires_warehouse_when_product_is_in_multiple_warehouses(): void
    {
        $ctx = $this->context();

        $inventoryA = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->postJson('/api/inventory/removeAssignedInventory', [
                'product_ids' => [
                    ['id' => $ctx['productA']->id],
                ],
            ]);

        $response->assertStatus(422);

        $this->assertSame(10, (int) $inventoryA->fresh()->quantity);
        $this->assertSame(20, (int) $inventoryB->fresh()->quantity);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_cross_company_inventory_update_is_forbidden(): void
    {
        $ctx = $this->context();

        $otherInventory = Inventory::create([
            'product_id' => $ctx['productB']->id,
            'warehouse_id' => $ctx['otherWarehouse']->id,
            'quantity' => 10,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->putJson('/api/inventory/' . $otherInventory->id, [
                'quantity' => 3,
            ]);

        $response->assertStatus(403);
        $this->assertSame(
            10,
            (int) $otherInventory->fresh()->quantity
        );
    }
}
