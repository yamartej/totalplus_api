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

class SaleTransactionVoidPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $company = Company::create([
            'name' => 'Phase 3C Company',
        ]);

        $seller = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 31000001,
            'name' => 'Phase 3C Customer',
            'address' => 'Testing',
            'phone' => '0000000000',
        ]);

        $pop = PointOfSale::create([
            'identifier' => 'POS-PHASE3C-' . uniqid(),
            'ubication' => 'Testing',
            'company_id' => $company->id,
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Phase 3C Warehouse A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Phase 3C Warehouse B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        $productA = Product::factory()->create([
            'name' => 'Phase 3C Product A',
            'final_cost' => 100,
            'wholesale_final_cost' => 90,
        ]);

        $productB = Product::factory()->create([
            'name' => 'Phase 3C Product B',
            'final_cost' => 200,
            'wholesale_final_cost' => 180,
        ]);

        return compact(
            'company',
            'seller',
            'customer',
            'pop',
            'warehouseA',
            'warehouseB',
            'productA',
            'productB'
        );
    }

    private function authHeaders(User $user): array
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 3C Sales Void'],
            ['description' => 'Phase 3C test role']
        );

        $permission = Permission::firstOrCreate(
            ['name' => 'sales.delete'],
            ['description' => 'Phase 3C sales delete permission']
        );

        $role->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        return [
            'Authorization' => 'Bearer '
                . $user
                    ->createToken('phase-3c-void-test')
                    ->plainTextToken,
        ];
    }

    private function createSale(array $ctx): Sale
    {
        return Sale::create([
            'customer_id' => $ctx['customer']->id,
            'seller_id' => $ctx['seller']->id,
            'pop_id' => $ctx['pop']->id,
            'total_amount' => 0,
            'type_of_sale' => 'normal',
            'company_id' => $ctx['company']->id,
        ]);
    }

    public function test_void_restores_each_exact_warehouse_and_records_kardex(): void
    {
        $ctx = $this->context();

        $inventoryA = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 4,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $ctx['productB']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 6,
        ]);

        $sale = $this->createSale($ctx);

        SaleDetail::create([
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
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->deleteJson('/api/sales/' . $sale->id);

        $response->assertStatus(204);

        $this->assertSame(
            6,
            (int) $inventoryA->fresh()->quantity
        );
        $this->assertSame(
            9,
            (int) $inventoryB->fresh()->quantity
        );

        $this->assertSame(
            2,
            InventoryMovement::where(
                'reference_type',
                'sale_void'
            )
                ->where('reference_id', $sale->id)
                ->count()
        );

        $this->assertDatabaseMissing(
            'sales',
            ['id' => $sale->id]
        );
        $this->assertDatabaseMissing(
            'sales_details',
            ['sale_id' => $sale->id]
        );
    }

    public function test_legacy_detail_without_warehouse_is_rejected_when_ambiguous(): void
    {
        $ctx = $this->context();

        $inventoryA = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 4,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 7,
        ]);

        $sale = $this->createSale($ctx);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'quantity' => 2,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->deleteJson('/api/sales/' . $sale->id);

        $response->assertStatus(422);

        $this->assertSame(
            4,
            (int) $inventoryA->fresh()->quantity
        );
        $this->assertSame(
            7,
            (int) $inventoryB->fresh()->quantity
        );

        $this->assertDatabaseHas(
            'sales',
            ['id' => $sale->id]
        );
        $this->assertDatabaseHas(
            'sales_details',
            ['sale_id' => $sale->id]
        );
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_void_rolls_back_all_restores_when_any_line_is_invalid(): void
    {
        $ctx = $this->context();

        $inventoryA = Inventory::create([
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 4,
        ]);

        Inventory::create([
            'product_id' => $ctx['productB']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 6,
        ]);

        Inventory::create([
            'product_id' => $ctx['productB']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 6,
        ]);

        $sale = $this->createSale($ctx);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $ctx['productB']->id,
            'quantity' => 3,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->deleteJson('/api/sales/' . $sale->id);

        $response->assertStatus(422);

        $this->assertSame(
            4,
            (int) $inventoryA->fresh()->quantity
        );

        $this->assertDatabaseHas(
            'sales',
            ['id' => $sale->id]
        );

        $this->assertSame(
            2,
            SaleDetail::where(
                'sale_id',
                $sale->id
            )->count()
        );

        $this->assertSame(0, InventoryMovement::count());
    }
}
