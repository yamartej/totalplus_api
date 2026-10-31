<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
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

/**
 * QA-018 integration gate: ONLY execute in disposable CI databases.
 *
 * RefreshDatabase may reset its target database. This test must never be
 * invoked against persistent XAMPP QA databases or production.
 */
class Qa018SaleVoidHttpGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_is_created_and_voided_exactly_once_via_authenticated_http(): void
    {
        // Fail closed if this class is inadvertently run against a local DB.
        $database = \DB::connection()->getDatabaseName();
        $this->assertSame('totalplus_ci_qa018', $database);

        $company = Company::create(['name' => 'QA018 CI only']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $role = Roles::create(['name' => 'QA018 CI salesperson']);
        foreach (['sales.create', 'sales.delete', 'sales.view', 'reports.view'] as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'CI-only QA018 permission']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);
        $token = $user->createToken('qa018-gate-ci-only')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 88001801,
            'name' => 'QA018 disposable customer',
            'address' => 'CI only',
            'phone' => '00000000',
        ]);
        $pos = PointOfSale::create([
            'identifier' => 'QA018-CI-' . uniqid(),
            'ubication' => 'CI only',
            'status' => 'open',
            'seller_id' => $user->id,
            'seller' => $user->name,
            'company_id' => $company->id,
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'QA018 Warehouse A',
            'description' => 'CI only',
            'address' => 'CI',
            'company_id' => $company->id,
        ]);
        $warehouseB = Warehouse::create([
            'name' => 'QA018 Warehouse B',
            'description' => 'CI only',
            'address' => 'CI',
            'company_id' => $company->id,
        ]);

        $batch = Batch::create([
            'name' => 'QA018 Received Batch ' . uniqid(),
            'description' => 'CI only',
            'quantity' => 20,
            'status' => 'received',
            'order_creation_date' => '2026-10-25 12:00:00',
        ]);
        $batch->company_id = $company->id;
        $batch->save();

        $product = Product::factory()->create([
            'name' => 'QA018 CI item',
            'price' => 40,
            'final_cost' => 40,
            'wholesale_final_cost' => 35,
            'batch_id' => $batch->id,
            'company_id' => $company->id,
        ]);

        $inventoryA = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseA->id,
            'quantity' => 7,
        ]);
        $inventoryB = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseB->id,
            'quantity' => 9,
        ]);

        $this->withHeaders($headers)->postJson('/api/sales', [
            'client_id' => $customer->id,
            'seller_id' => $user->id,
            'pop_id' => $pos->id,
            'type_of_sale' => 'normal',
            'carts' => [
                [
                    'productId' => $product->id,
                    'warehouse_id' => $warehouseA->id,
                    'quantity' => 2,
                ],
                [
                    'productId' => $product->id,
                    'warehouse_id' => $warehouseB->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('105.00', (string) $sale->total_amount);
        $this->assertSame(2, SaleDetail::where('sale_id', $sale->id)->count());
        $this->assertSame(5, (int) $inventoryA->fresh()->quantity);
        $this->assertSame(8, (int) $inventoryB->fresh()->quantity);
        $this->assertSame(2, InventoryMovement::query()
            ->where('reference_type', 'sale')
            ->where('reference_id', $sale->id)->count());

        $this->withHeaders($headers)
            ->deleteJson('/api/sales/' . $sale->id)
            ->assertNoContent();

        $this->assertSame(7, (int) $inventoryA->fresh()->quantity);
        $this->assertSame(9, (int) $inventoryB->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouseA->id,
            'product_id' => $product->id,
            'quantity_delta' => 2,
            'balance_before' => 5,
            'balance_after' => 7,
            'reference_type' => 'sale_void',
            'reference_id' => $sale->id,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouseB->id,
            'product_id' => $product->id,
            'quantity_delta' => 1,
            'balance_before' => 8,
            'balance_after' => 9,
            'reference_type' => 'sale_void',
            'reference_id' => $sale->id,
        ]);
        $this->assertSame(2, InventoryMovement::query()
            ->where('reference_type', 'sale_void')
            ->where('reference_id', $sale->id)->count());

        $this->assertNotNull($sale->fresh()->voided_at);
        $this->assertSame('manual', $sale->fresh()->void_reason);
        $this->assertSame($user->id, (int) $sale->fresh()->voided_by);
        $this->assertSame(2, SaleDetail::where('sale_id', $sale->id)->count());

        $this->withHeaders($headers)
            ->getJson('/api/sales')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $sale->id,
                'void_reason' => 'manual',
            ]);

        $date = $sale->created_at->format('Y-m-d');
        $this->withHeaders($headers)
            ->getJson('/api/reports/sales/period/' . $date . '/' . $date)
            ->assertOk()
            ->assertExactJson([]);

        $this->withHeaders($headers)
            ->deleteJson('/api/sales/' . $sale->id)
            ->assertStatus(422);

        $this->assertSame(7, (int) $inventoryA->fresh()->quantity);
        $this->assertSame(9, (int) $inventoryB->fresh()->quantity);
        $this->assertSame(2, InventoryMovement::query()
            ->where('reference_type', 'sale_void')
            ->where('reference_id', $sale->id)->count());
    }
}
