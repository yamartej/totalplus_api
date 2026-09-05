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

class SaleTransactionStorePhase3Test extends TestCase
{
    use RefreshDatabase;

    private function grantCreatePermission(User $user): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 3 Transactional Sales'],
            ['description' => 'Test-only Phase 3 sales role']
        );

        $permission = Permission::firstOrCreate(
            ['name' => 'sales.create'],
            ['description' => 'Phase 3 sales create permission']
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
        $this->grantCreatePermission($user);

        return [
            'Authorization' => 'Bearer '
                . $user
                    ->createToken('phase-3-store-test')
                    ->plainTextToken,
        ];
    }

    private function context(): array
    {
        $company = Company::create([
            'name' => 'Phase 3 Company',
        ]);

        $otherCompany = Company::create([
            'name' => 'Phase 3 Other Company',
        ]);

        $seller = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 30000001,
            'name' => 'Phase 3 Customer',
            'address' => 'Testing',
            'phone' => '0000000000',
        ]);

        $pop = PointOfSale::create([
            'identifier' => 'POS-PHASE3-' . uniqid(),
            'ubication' => 'Testing',
            'company_id' => $company->id,
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Phase 3 Warehouse A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Phase 3 Warehouse B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        $otherWarehouse = Warehouse::create([
            'name' => 'Phase 3 Other Warehouse',
            'description' => 'Other',
            'address' => 'Other',
            'company_id' => $otherCompany->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Phase 3 Product',
            'price' => 100,
            'final_cost' => 100,
            'wholesale_final_cost' => 90,
        ]);

        $product->company_id = $company->id;
        $product->save();

        return compact(
            'company',
            'otherCompany',
            'seller',
            'customer',
            'pop',
            'warehouseA',
            'warehouseB',
            'otherWarehouse',
            'product'
        );
    }

    private function payload(
        array $ctx,
        int $quantity,
        ?int $warehouseId = null
    ): array {
        $cart = [
            'productId' => $ctx['product']->id,
            'quantity' => $quantity,
        ];

        if ($warehouseId !== null) {
            $cart['warehouse_id'] = $warehouseId;
        }

        return [
            'client_id' => $ctx['customer']->id,
            'seller_id' => $ctx['seller']->id,
            'pop_id' => $ctx['pop']->id,
            'total' => 1.23,
            'type_of_sale' => 'normal',
            'carts' => [$cart],
        ];
    }

    public function test_explicit_warehouse_creates_exact_issue_and_server_total(): void
    {
        $ctx = $this->context();

        $inventoryA = Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 6,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson(
                '/api/sales',
                $this->payload(
                    $ctx,
                    3,
                    $ctx['warehouseB']->id
                )
            );

        $response->assertStatus(201);

        $sale = Sale::latest('id')->firstOrFail();

        $this->assertSame(
            '270.00',
            (string) $sale->total_amount
        );

        $detail = SaleDetail::where(
            'sale_id',
            $sale->id
        )->firstOrFail();

        $this->assertSame(
            $ctx['warehouseB']->id,
            (int) $detail->warehouse_id
        );
        $this->assertSame(
            '90.00',
            (string) $detail->unit_price
        );

        $this->assertSame(
            10,
            (int) $inventoryA->fresh()->quantity
        );
        $this->assertSame(
            3,
            (int) $inventoryB->fresh()->quantity
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'company_id' => $ctx['company']->id,
                'warehouse_id' => $ctx['warehouseB']->id,
                'product_id' => $ctx['product']->id,
                'type' => InventoryMovement::TYPE_ISSUE,
                'quantity_delta' => -3,
                'balance_before' => 6,
                'balance_after' => 3,
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
            ]
        );
    }

    public function test_insufficient_stock_rolls_back_sale_details_and_kardex(): void
    {
        $ctx = $this->context();

        $inventory = Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson(
                '/api/sales',
                $this->payload(
                    $ctx,
                    5,
                    $ctx['warehouseA']->id
                )
            );

        $response->assertStatus(422);

        $this->assertSame(
            2,
            (int) $inventory->fresh()->quantity
        );
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleDetail::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_missing_warehouse_is_rejected_when_stock_is_in_multiple_warehouses(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 5,
        ]);

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 5,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson(
                '/api/sales',
                $this->payload($ctx, 1)
            );

        $response->assertStatus(422);

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleDetail::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_cross_company_warehouse_is_rejected(): void
    {
        $ctx = $this->context();

        $otherInventory = Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['otherWarehouse']->id,
            'quantity' => 5,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['seller']))
            ->postJson(
                '/api/sales',
                $this->payload(
                    $ctx,
                    1,
                    $ctx['otherWarehouse']->id
                )
            );

        $response->assertStatus(422);

        $this->assertSame(
            5,
            (int) $otherInventory->fresh()->quantity
        );
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleDetail::count());
        $this->assertSame(0, InventoryMovement::count());
    }
}
