<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Roles;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleBaselineTest extends TestCase
{
    use RefreshDatabase;

    private function context(int $stockA = 10, int $stockB = 10): array
    {
        $company = Company::create([
            'name' => 'Baseline Company',
        ]);

        $seller = User::factory()->create();

        // Evitamos depender de que company_id esté presente en la factory de User.
        if (in_array('company_id', $seller->getFillable(), true)) {
            $seller->company_id = $company->id;
            $seller->save();
        }

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 10000001,
            'name' => 'Cliente Baseline',
            'address' => 'Dirección de prueba',
            'phone' => '0000000000',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Almacén Baseline',
            'description' => 'Warehouse for phase 0',
            'address' => 'Testing',
            'company_id' => $company->id,
        ]);

        $pop = PointOfSale::create([
            'identifier' => 'POS-PHASE0-' . uniqid(),
            'ubication' => 'Testing',
            'company_id' => $company->id,
        ]);

        $productA = Product::factory()->create([
            'name' => 'Producto A',
            'price' => 100,
            'final_cost' => 100,
            'wholesale_final_cost' => 90,
        ]);

        $productB = Product::factory()->create([
            'name' => 'Producto B',
            'price' => 200,
            'final_cost' => 200,
            'wholesale_final_cost' => 180,
        ]);

        $inventoryA = Inventory::create([
            'product_id' => $productA->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $stockA,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $productB->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $stockB,
        ]);

        return compact(
            'company',
            'seller',
            'customer',
            'warehouse',
            'pop',
            'productA',
            'productB',
            'inventoryA',
            'inventoryB'
        );
    }

    private function grantApiPermissions(User $user, array $permissionNames): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 0 Sales Baseline'],
            ['description' => 'Test-only role for Phase 0 sales characterization']
        );

        $permissionIds = collect($permissionNames)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 0 characterization permission']
                )->id;
            })
            ->all();

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function authHeaders(User $user): array
    {
        $this->grantApiPermissions($user, [
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.delete',
        ]);

        return [
            'Authorization' => 'Bearer ' . $user->createToken('phase-0-test')->plainTextToken,
        ];
    }

    public function test_current_sale_contract_creates_sale_and_details(): void
    {
        $ctx = $this->context();

        $payload = [
            'client_id' => (string) $ctx['customer']->id,
            'seller_id' => (string) $ctx['seller']->id,
            'pop_id' => (string) $ctx['pop']->id,
            'total' => 400,
            'type_of_sale' => 'normal',
            'carts' => [
                ['productId' => $ctx['productA']->id, 'quantity' => 2],
                ['productId' => $ctx['productB']->id, 'quantity' => 1],
            ],
        ];

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson('/api/sales', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'customer_id' => $ctx['customer']->id,
            'seller_id' => $ctx['seller']->id,
            'pop_id' => $ctx['pop']->id,
            'total_amount' => 400,
            'type_of_sale' => 'normal',
        ]);

        $sale = Sale::latest('id')->firstOrFail();

        $this->assertDatabaseHas('sales_details', [
            'sale_id' => $sale->id,
            'product_id' => $ctx['productA']->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('sales_details', [
            'sale_id' => $sale->id,
            'product_id' => $ctx['productB']->id,
            'quantity' => 1,
        ]);

        $this->assertSame(8, (int) $ctx['inventoryA']->fresh()->quantity);
        $this->assertSame(9, (int) $ctx['inventoryB']->fresh()->quantity);
    }

    /**
     * Documenta un defecto actual:
     * el backend confía en el total enviado por el cliente.
     *
     * @group baseline-defect
     */
    public function test_current_backend_accepts_client_supplied_total(): void
    {
        $ctx = $this->context();

        $payload = [
            'client_id' => (string) $ctx['customer']->id,
            'seller_id' => (string) $ctx['seller']->id,
            'pop_id' => (string) $ctx['pop']->id,
            'total' => 1.23,
            'type_of_sale' => 'normal',
            'carts' => [
                ['productId' => $ctx['productA']->id, 'quantity' => 1],
            ],
        ];

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson('/api/sales', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'customer_id' => $ctx['customer']->id,
            'total_amount' => 1.23,
        ]);
    }

    /**
     * Documenta un defecto actual:
     * SaleController::store no rechaza cantidad mayor que el saldo.
     *
     * @group baseline-defect
     */
    public function test_current_sale_can_drive_inventory_negative(): void
    {
        $ctx = $this->context(stockA: 2);

        $payload = [
            'client_id' => (string) $ctx['customer']->id,
            'seller_id' => (string) $ctx['seller']->id,
            'pop_id' => (string) $ctx['pop']->id,
            'total' => 500,
            'type_of_sale' => 'normal',
            'carts' => [
                ['productId' => $ctx['productA']->id, 'quantity' => 5],
            ],
        ];

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson('/api/sales', $payload);

        $response->assertStatus(201);
        $this->assertSame(-3, (int) $ctx['inventoryA']->fresh()->quantity);
    }

    /**
     * Documenta un defecto actual:
     * al destruir una venta con varios productos, la suma total de cantidades
     * se devuelve al inventario del primer producto.
     *
     * @group baseline-defect
     */
    public function test_current_multi_product_sale_void_restores_stock_incorrectly(): void
    {
        $ctx = $this->context(stockA: 10, stockB: 10);

        $payload = [
            'client_id' => (string) $ctx['customer']->id,
            'seller_id' => (string) $ctx['seller']->id,
            'pop_id' => (string) $ctx['pop']->id,
            'total' => 800,
            'type_of_sale' => 'normal',
            'carts' => [
                ['productId' => $ctx['productA']->id, 'quantity' => 2],
                ['productId' => $ctx['productB']->id, 'quantity' => 3],
            ],
        ];

        $createResponse = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson('/api/sales', $payload);

        $createResponse->assertStatus(201);

        $sale = Sale::latest('id')->firstOrFail();

        $this->assertSame(8, (int) $ctx['inventoryA']->fresh()->quantity);
        $this->assertSame(7, (int) $ctx['inventoryB']->fresh()->quantity);

        $deleteResponse = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->deleteJson('/api/sales/' . $sale->id);

        $deleteResponse->assertStatus(204);

        // Caracterización del defecto actual:
        // primer producto: 8 + (2 + 3) = 13
        // segundo producto: permanece en 7.
        $this->assertSame(13, (int) $ctx['inventoryA']->fresh()->quantity);
        $this->assertSame(7, (int) $ctx['inventoryB']->fresh()->quantity);
    }
}
