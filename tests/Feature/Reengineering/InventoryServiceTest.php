<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventoryService::class);
    }

    private function inventoryContext(): array
    {
        $company = Company::create([
            'name' => 'Phase 2 Inventory Company',
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Warehouse A',
            'description' => 'Phase 2 warehouse A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Warehouse B',
            'description' => 'Phase 2 warehouse B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create();
        $product->company_id = $company->id;
        $product->save();

        return compact(
            'company',
            'warehouseA',
            'warehouseB',
            'product'
        );
    }

    public function test_receive_creates_balance_and_kardex_movement(): void
    {
        $ctx = $this->inventoryContext();

        $movement = $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            10
        );

        $this->assertSame(
            InventoryMovement::TYPE_RECEIVE,
            $movement->type
        );

        $this->assertSame(10, (int) $movement->quantity_delta);
        $this->assertSame(0, (int) $movement->balance_before);
        $this->assertSame(10, (int) $movement->balance_after);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);
    }

    public function test_issue_reduces_exact_warehouse_balance(): void
    {
        $ctx = $this->inventoryContext();

        $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            10
        );

        $movement = $this->service->issue(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            3
        );

        $this->assertSame(-3, (int) $movement->quantity_delta);
        $this->assertSame(10, (int) $movement->balance_before);
        $this->assertSame(7, (int) $movement->balance_after);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 7,
        ]);
    }

    public function test_issue_cannot_make_inventory_negative(): void
    {
        $ctx = $this->inventoryContext();

        $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            7
        );

        $movementsBefore = InventoryMovement::count();

        try {
            $this->service->issue(
                $ctx['company']->id,
                $ctx['warehouseA']->id,
                $ctx['product']->id,
                8
            );

            $this->fail(
                'Expected insufficient stock exception was not thrown.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'Insufficient stock for this operation.',
                $exception->getMessage()
            );
        }

        $inventory = Inventory::where(
            'product_id',
            $ctx['product']->id
        )
            ->where(
                'warehouse_id',
                $ctx['warehouseA']->id
            )
            ->firstOrFail();

        $this->assertSame(7, (int) $inventory->quantity);
        $this->assertSame(
            $movementsBefore,
            InventoryMovement::count()
        );
    }

    public function test_adjust_sets_absolute_balance_and_records_delta(): void
    {
        $ctx = $this->inventoryContext();

        $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            10
        );

        $movement = $this->service->adjust(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            15
        );

        $this->assertSame(
            InventoryMovement::TYPE_ADJUSTMENT,
            $movement->type
        );

        $this->assertSame(5, (int) $movement->quantity_delta);
        $this->assertSame(10, (int) $movement->balance_before);
        $this->assertSame(15, (int) $movement->balance_after);
    }

    public function test_same_product_has_independent_balance_per_warehouse(): void
    {
        $ctx = $this->inventoryContext();

        $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            10
        );

        $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseB']->id,
            $ctx['product']->id,
            20
        );

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $this->assertSame(
            2,
            Inventory::where(
                'product_id',
                $ctx['product']->id
            )->count()
        );
    }

    public function test_warehouse_from_another_company_is_rejected(): void
    {
        $ctx = $this->inventoryContext();

        $otherCompany = Company::create([
            'name' => 'Other Company',
        ]);

        $otherWarehouse = Warehouse::create([
            'name' => 'Other Warehouse',
            'description' => 'Other company warehouse',
            'address' => 'Other',
            'company_id' => $otherCompany->id,
        ]);

        try {
            $this->service->receive(
                $ctx['company']->id,
                $otherWarehouse->id,
                $ctx['product']->id,
                10
            );

            $this->fail(
                'Expected cross-company warehouse rejection.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'Warehouse does not belong to the selected company.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, Inventory::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_product_from_another_company_is_rejected(): void
    {
        $ctx = $this->inventoryContext();

        $otherCompany = Company::create([
            'name' => 'Product Company',
        ]);

        $ctx['product']->company_id = $otherCompany->id;
        $ctx['product']->save();

        try {
            $this->service->receive(
                $ctx['company']->id,
                $ctx['warehouseA']->id,
                $ctx['product']->id,
                10
            );

            $this->fail(
                'Expected cross-company product rejection.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'Product does not belong to the selected company.',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, Inventory::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_inventory_movement_cannot_be_updated_or_deleted(): void
    {
        $ctx = $this->inventoryContext();

        $movement = $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            10
        );

        try {
            $movement->update([
                'notes' => 'Attempted modification',
            ]);

            $this->fail(
                'Inventory movement update should be rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Inventory movements are immutable and cannot be updated.',
                $exception->getMessage()
            );
        }

        $movement = $movement->fresh();

        try {
            $movement->delete();

            $this->fail(
                'Inventory movement delete should be rejected.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Inventory movements are immutable and cannot be deleted.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('inventory_movements', [
            'id' => $movement->id,
            'quantity_delta' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
        ]);
    }

    public function test_transfer_moves_stock_between_warehouses_atomically(): void
    {
        $ctx = $this->inventoryContext();

        $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            20
        );

        $movements = $this->service->transfer(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['warehouseB']->id,
            $ctx['product']->id,
            6
        );

        $this->assertSame(
            InventoryMovement::TYPE_TRANSFER_OUT,
            $movements['out']->type
        );

        $this->assertSame(
            InventoryMovement::TYPE_TRANSFER_IN,
            $movements['in']->type
        );

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 14,
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 6,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $ctx['warehouseA']->id,
            'type' => InventoryMovement::TYPE_TRANSFER_OUT,
            'quantity_delta' => -6,
            'balance_before' => 20,
            'balance_after' => 14,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $ctx['warehouseB']->id,
            'type' => InventoryMovement::TYPE_TRANSFER_IN,
            'quantity_delta' => 6,
            'balance_before' => 0,
            'balance_after' => 6,
        ]);
    }

    public function test_failed_transfer_rolls_back_both_warehouses(): void
    {
        $ctx = $this->inventoryContext();

        $this->service->receive(
            $ctx['company']->id,
            $ctx['warehouseA']->id,
            $ctx['product']->id,
            5
        );

        $movementsBefore = InventoryMovement::count();

        try {
            $this->service->transfer(
                $ctx['company']->id,
                $ctx['warehouseA']->id,
                $ctx['warehouseB']->id,
                $ctx['product']->id,
                10
            );

            $this->fail(
                'Expected insufficient transfer stock exception.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'Insufficient stock for this transfer.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('inventories', [
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
        ]);

        $this->assertSame(
            $movementsBefore,
            InventoryMovement::count()
        );
    }
}
