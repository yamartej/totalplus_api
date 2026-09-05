<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Roles;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleDetailTransactionPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $company = Company::create([
            'name' => 'Phase 3D Company',
        ]);

        $otherCompany = Company::create([
            'name' => 'Phase 3D Other Company',
        ]);

        $seller = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $otherSeller = User::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 32000001,
            'name' => 'Phase 3D Customer',
            'address' => 'Testing',
            'phone' => '0000000000',
        ]);

        $pop = PointOfSale::create([
            'identifier' => 'POS-PHASE3D-' . uniqid(),
            'ubication' => 'Testing',
            'company_id' => $company->id,
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Phase 3D Warehouse A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Phase 3D Warehouse B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        $productA = Product::factory()->create([
            'name' => 'Phase 3D Product A',
            'final_cost' => 100,
            'wholesale_final_cost' => 90,
        ]);

        $productB = Product::factory()->create([
            'name' => 'Phase 3D Product B',
            'final_cost' => 200,
            'wholesale_final_cost' => 180,
        ]);

        return compact(
            'company',
            'otherCompany',
            'seller',
            'otherSeller',
            'customer',
            'pop',
            'warehouseA',
            'warehouseB',
            'productA',
            'productB'
        );
    }

    private function grantPermissions(
        User $user,
        array $names
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 3D Sale Detail'],
            ['description' => 'Phase 3D test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 3D permission']
                )->id;
            })
            ->all();

        $role->permissions()->syncWithoutDetaching(
            $permissionIds
        );

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);
    }

    private function authHeaders(
        User $user,
        array $permissions
    ): array {
        $this->grantPermissions(
            $user,
            $permissions
        );

        return [
            'Authorization' => 'Bearer '
                . $user
                    ->createToken('phase-3d-detail-test')
                    ->plainTextToken,
        ];
    }

    private function createSale(
        array $ctx,
        $total
    ): Sale {
        return Sale::create([
            'customer_id' => $ctx['customer']->id,
            'seller_id' => $ctx['seller']->id,
            'pop_id' => $ctx['pop']->id,
            'total_amount' => $total,
            'type_of_sale' => 'normal',
            'company_id' => $ctx['company']->id,
        ]);
    }

    public function test_update_increase_issues_only_quantity_delta_and_recalculates_total(): void
    {
        $ctx = $this->context();

        $inventory = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 8,
        ]);

        $sale = $this->createSale($ctx, 200);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    ['sales.update']
                )
            )
            ->putJson(
                '/api/sales/detail/' . $detail->id,
                [
                    'sale_id' => $sale->id,
                    'quantity' => 4,
                    'total_amount' => 1,
                    'new_total_amount' => 1,
                ]
            );

        $response->assertStatus(200);

        $this->assertSame(
            6,
            (int) $inventory->fresh()->quantity
        );

        $this->assertSame(
            4,
            (int) $detail->fresh()->quantity
        );

        $this->assertSame(
            '100.00',
            (string) $detail->fresh()->unit_price
        );

        $this->assertSame(
            '400.00',
            (string) $sale->fresh()->total_amount
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'warehouse_id' => $ctx['warehouseA']->id,
                'product_id' => $ctx['productA']->id,
                'type' => InventoryMovement::TYPE_ISSUE,
                'quantity_delta' => -2,
                'reference_type' => 'sale_detail_update',
                'reference_id' => $detail->id,
            ]
        );
    }

    public function test_update_decrease_receives_only_quantity_delta(): void
    {
        $ctx = $this->context();

        $inventory = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 6,
        ]);

        $sale = $this->createSale($ctx, 400);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 4,
            'unit_price' => 100,
        ]);

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    ['sales.update']
                )
            )
            ->putJson(
                '/api/sales/detail/' . $detail->id,
                ['quantity' => 1]
            );

        $response->assertStatus(200);

        $this->assertSame(
            9,
            (int) $inventory->fresh()->quantity
        );

        $this->assertSame(
            1,
            (int) $detail->fresh()->quantity
        );

        $this->assertSame(
            '100.00',
            (string) $sale->fresh()->total_amount
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'type' => InventoryMovement::TYPE_RECEIVE,
                'quantity_delta' => 3,
                'reference_type' => 'sale_detail_update',
                'reference_id' => $detail->id,
            ]
        );
    }

    public function test_update_insufficient_stock_rolls_back_detail_sale_and_inventory(): void
    {
        $ctx = $this->context();

        $inventory = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 1,
        ]);

        $sale = $this->createSale($ctx, 200);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    ['sales.update']
                )
            )
            ->putJson(
                '/api/sales/detail/' . $detail->id,
                ['quantity' => 5]
            );

        $response->assertStatus(422);

        $this->assertSame(
            1,
            (int) $inventory->fresh()->quantity
        );

        $this->assertSame(
            2,
            (int) $detail->fresh()->quantity
        );

        $this->assertSame(
            '200.00',
            (string) $sale->fresh()->total_amount
        );

        $this->assertSame(
            0,
            InventoryMovement::count()
        );
    }

    public function test_remove_detail_restores_exact_inventory_and_recalculates_remaining_total(): void
    {
        $ctx = $this->context();

        $inventoryA = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 8,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $ctx['productB']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 7,
        ]);

        $sale = $this->createSale($ctx, 740);

        $detailA = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productB']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 3,
            'unit_price' => 180,
        ]);

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    ['sales.delete']
                )
            )
            ->deleteJson(
                '/api/sales/detail/' . $detailA->id
            );

        $response->assertStatus(204);

        $this->assertSame(
            10,
            (int) $inventoryA->fresh()->quantity
        );

        $this->assertSame(
            7,
            (int) $inventoryB->fresh()->quantity
        );

        $this->assertDatabaseMissing(
            'sales_details',
            ['id' => $detailA->id]
        );

        $this->assertDatabaseHas(
            'sales',
            [
                'id' => $sale->id,
                'total_amount' => 540,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'type' => InventoryMovement::TYPE_RECEIVE,
                'quantity_delta' => 2,
                'reference_type' => 'sale_detail_remove',
                'reference_id' => $detailA->id,
            ]
        );
    }

    public function test_remove_last_detail_restores_inventory_and_deletes_sale(): void
    {
        $ctx = $this->context();

        $inventory = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 8,
        ]);

        $sale = $this->createSale($ctx, 200);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    ['sales.delete']
                )
            )
            ->deleteJson(
                '/api/sales/detail/' . $detail->id
            );

        $response->assertStatus(204);

        $this->assertSame(
            10,
            (int) $inventory->fresh()->quantity
        );

        $this->assertDatabaseMissing(
            'sales_details',
            ['id' => $detail->id]
        );

        $this->assertDatabaseMissing(
            'sales',
            ['id' => $sale->id]
        );
    }

    public function test_other_company_user_cannot_update_sale_detail(): void
    {
        $ctx = $this->context();

        $inventory = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 8,
        ]);

        $sale = $this->createSale($ctx, 200);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['otherSeller'],
                    ['sales.update']
                )
            )
            ->putJson(
                '/api/sales/detail/' . $detail->id,
                ['quantity' => 3]
            );

        $response->assertStatus(403);

        $this->assertSame(
            8,
            (int) $inventory->fresh()->quantity
        );

        $this->assertSame(
            2,
            (int) $detail->fresh()->quantity
        );

        $this->assertSame(
            '200.00',
            (string) $sale->fresh()->total_amount
        );

        $this->assertSame(
            0,
            InventoryMovement::count()
        );
    }
}
