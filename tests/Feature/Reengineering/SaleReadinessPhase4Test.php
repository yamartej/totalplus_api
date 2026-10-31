<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReadinessPhase4Test extends TestCase
{
    use RefreshDatabase;

    public function test_sale_requires_received_batch_positive_prices_and_assigned_stock(): void
    {
        $company = Company::create([
            'name' => 'Phase 4 Sale Readiness Company',
        ]);
        $seller = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Sale Readiness'],
            ['description' => 'Sale readiness workflow testing']
        );

        foreach (['inventory.view', 'products.update', 'sales.create'] as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'Phase 4 sale readiness permission']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $seller->roles()->syncWithoutDetaching([$role->id]);

        $headers = [
            'Authorization' => 'Bearer '
                . $seller->createToken('sale-readiness-test')->plainTextToken,
        ];

        $warehouse = Warehouse::create([
            'name' => 'Sale Readiness Warehouse',
            'description' => 'Testing',
            'address' => 'Testing',
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 77700001,
            'name' => 'Sale Readiness Customer',
            'address' => 'Testing',
            'phone' => '0000000000',
        ]);

        $pop = PointOfSale::create([
            'identifier' => 'QA-SALE-READINESS-' . uniqid(),
            'ubication' => 'Testing',
            'company_id' => $company->id,
            'status' => 'open',
            'seller_id' => $seller->id,
            'seller' => $seller->name,
        ]);

        $batch = Batch::create([
            'name' => 'Sale Readiness Batch',
            'description' => 'Purchase not yet received',
            'quantity' => 2,
            'status' => 'created',
            'order_creation_date' => '2026-10-25 12:00:00',
        ]);
        $batch->company_id = $company->id;
        $batch->save();

        $product = Product::factory()->create([
            'name' => 'Sale Readiness Product',
            'price' => 25,
            'quantity' => 2,
            'batch_id' => $batch->id,
            'final_cost' => 0,
            'wholesale_final_cost' => 0,
        ]);
        $product->company_id = $company->id;
        $product->save();

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
        ]);

        $salePayload = [
            'client_id' => $customer->id,
            'seller_id' => $seller->id,
            'pop_id' => $pop->id,
            'type_of_sale' => 'normal',
            'carts' => [
                [
                    'productId' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $pricePayload = [
            'id' => $product->id,
            'final_cost' => 40,
            'wholesale_final_cost' => 35,
        ];

        // The purchase price is not a sale price.
        $this->withHeaders($headers)
            ->getJson('/api/inventory?sellable=1')
            ->assertOk()
            ->assertExactJson([]);

        $this->withHeaders($headers)
            ->putJson('/api/products/update-final-cost', $pricePayload)
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->postJson('/api/sales', $salePayload)
            ->assertStatus(422);

        $batch->status = 'received';
        $batch->save();

        // Received but not priced is still not sellable.
        $this->withHeaders($headers)
            ->getJson('/api/inventory?sellable=1')
            ->assertOk()
            ->assertExactJson([]);

        $this->withHeaders($headers)
            ->postJson('/api/sales', $salePayload)
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->putJson('/api/products/update-final-cost', $pricePayload)
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/inventory?sellable=1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['product_id' => $product->id]);

        // Closing the cash register must block the API even if the caller
        // bypasses the frontend. The failed attempt cannot debit stock.
        $pop->status = 'closed';
        $pop->save();

        $this->withHeaders($headers)
            ->postJson('/api/sales', $salePayload)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'La caja debe estar abierta para registrar ventas.'
            );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseCount('sales', 0);

        // An open register is not enough: the seller must be assigned.
        $pop->status = 'open';
        $pop->seller_id = null;
        $pop->seller = null;
        $pop->save();

        $this->withHeaders($headers)
            ->postJson('/api/sales', $salePayload)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'El vendedor no está asignado a la caja abierta.'
            );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseCount('sales', 0);

        // Only after reopening and assigning the seller may stock move.
        $pop->seller_id = $seller->id;
        $pop->seller = $seller->name;
        $pop->save();

        $this->withHeaders($headers)
            ->postJson('/api/sales', $salePayload)
            ->assertCreated();

        $this->assertDatabaseHas('sales', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'total_amount' => 40,
        ]);

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 0,
        ]);
    }
}
