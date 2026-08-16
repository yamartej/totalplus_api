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

class InventoryKardexPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function grantApiPermissions(
        User $user,
        array $permissionNames
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 2D Inventory Kardex'],
            ['description' => 'Test-only role for inventory transfer and Kardex']
        );

        $permissionIds = collect($permissionNames)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 2D inventory permission']
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
            'inventory.update',
        ]);

        return [
            'Authorization' => 'Bearer ' .
                $user->createToken('phase-2d-inventory-test')->plainTextToken,
        ];
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 2D Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 2D Company B',
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
            'name' => 'Other Warehouse',
            'description' => 'Other',
            'address' => 'Other',
            'company_id' => $companyB->id,
        ]);

        $productA = Product::factory()->create();
        $productA->company_id = $companyA->id;
        $productA->save();

        $productA2 = Product::factory()->create();
        $productA2->company_id = $companyA->id;
        $productA2->save();

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
            'productA2',
            'productB',
            'userA'
        );
    }

    public function test_transfer_endpoint_moves_stock_and_creates_two_kardex_entries(): void
    {
        $ctx = $this->context();

        app(InventoryService::class)->receive(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA']->id,
            20
        );

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->postJson('/api/inventory/transfer', [
                'source_warehouse_id' => $ctx['warehouseA']->id,
                'destination_warehouse_id' => $ctx['warehouseB']->id,
                'product_id' => $ctx['productA']->id,
                'quantity' => 6,
                'reference_type' => 'manual_transfer',
                'reference_id' => 1001,
                'notes' => 'Phase 2D transfer test',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 14,
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 6,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $ctx['companyA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'product_id' => $ctx['productA']->id,
            'type' => InventoryMovement::TYPE_TRANSFER_OUT,
            'quantity_delta' => -6,
            'reference_type' => 'manual_transfer',
            'reference_id' => 1001,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $ctx['companyA']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'product_id' => $ctx['productA']->id,
            'type' => InventoryMovement::TYPE_TRANSFER_IN,
            'quantity_delta' => 6,
            'reference_type' => 'manual_transfer',
            'reference_id' => 1001,
        ]);
    }

    public function test_transfer_to_another_company_is_rejected_and_rolled_back(): void
    {
        $ctx = $this->context();

        app(InventoryService::class)->receive(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA']->id,
            10
        );

        $movementsBefore = InventoryMovement::count();

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->postJson('/api/inventory/transfer', [
                'source_warehouse_id' => $ctx['warehouseA']->id,
                'destination_warehouse_id' => $ctx['otherWarehouse']->id,
                'product_id' => $ctx['productA']->id,
                'quantity' => 4,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseMissing('inventories', [
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['otherWarehouse']->id,
        ]);

        $this->assertSame(
            $movementsBefore,
            InventoryMovement::count()
        );
    }

    public function test_insufficient_transfer_stock_rolls_back_destination_creation(): void
    {
        $ctx = $this->context();

        app(InventoryService::class)->receive(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA']->id,
            5
        );

        $movementsBefore = InventoryMovement::count();

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->postJson('/api/inventory/transfer', [
                'source_warehouse_id' => $ctx['warehouseA']->id,
                'destination_warehouse_id' => $ctx['warehouseB']->id,
                'product_id' => $ctx['productA']->id,
                'quantity' => 8,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('inventories', [
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
        ]);

        $this->assertSame(
            $movementsBefore,
            InventoryMovement::count()
        );
    }

    public function test_kardex_is_scoped_to_authenticated_users_company(): void
    {
        $ctx = $this->context();
        $service = app(InventoryService::class);

        $service->receive(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA']->id,
            10
        );

        $service->receive(
            $ctx['companyB']->id,
            $ctx['otherWarehouse']->id,
            $ctx['productB']->id,
            30
        );

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->getJson('/api/inventory/kardex');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.company_id',
            $ctx['companyA']->id
        );

        $response->assertJsonMissing([
            'company_id' => $ctx['companyB']->id,
        ]);
    }

    public function test_kardex_can_filter_by_warehouse_product_and_type(): void
    {
        $ctx = $this->context();
        $service = app(InventoryService::class);

        $service->receive(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA']->id,
            10
        );

        $service->issue(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA']->id,
            2
        );

        $service->receive(
            $ctx['companyA']->id,
            $ctx['warehouseB']->id,
            $ctx['productA']->id,
            20
        );

        $service->receive(
            $ctx['companyA']->id,
            $ctx['warehouseA']->id,
            $ctx['productA2']->id,
            7
        );

        $query = http_build_query([
            'warehouse_id' => $ctx['warehouseA']->id,
            'product_id' => $ctx['productA']->id,
            'type' => InventoryMovement::TYPE_ISSUE,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['userA']))
            ->getJson('/api/inventory/kardex?' . $query);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.warehouse_id',
            $ctx['warehouseA']->id
        );

        $response->assertJsonPath(
            'data.0.product_id',
            $ctx['productA']->id
        );

        $response->assertJsonPath(
            'data.0.type',
            InventoryMovement::TYPE_ISSUE
        );

        $response->assertJsonPath(
            'data.0.quantity_delta',
            -2
        );
    }
}
