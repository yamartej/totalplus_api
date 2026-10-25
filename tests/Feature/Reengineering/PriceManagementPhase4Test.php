<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Cost;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceManagementPhase4Test extends TestCase
{
    use RefreshDatabase;

    public function test_batches_and_products_are_visible_before_optional_costs_and_prices_require_receipt(): void
    {
        $company = Company::create(['name' => 'Price Workflow Company']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $role = Roles::firstOrCreate(
            ['name' => 'Price Workflow Test'],
            ['description' => 'Price workflow testing']
        );

        foreach (['batches.view', 'products.view', 'products.update'] as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['description' => 'Price workflow test permission']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);

        $headers = [
            'Authorization' => 'Bearer '
                . $user->createToken('price-workflow')->plainTextToken,
        ];

        $batch = Batch::create([
            'name' => 'QA Price Batch',
            'description' => 'Batch without additional costs',
            'quantity' => 2,
            'status' => 'created',
            'order_creation_date' => '2026-10-25 12:00:00',
        ]);
        $batch->company_id = $company->id;
        $batch->save();

        $category = Category::factory()->create();
        $product = Product::create([
            'name' => 'QA Price Product',
            'description' => 'Purchase price is not retail price',
            'price' => 25,
            'quantity' => 2,
            'category_id' => $category->id,
            'batch_id' => $batch->id,
            'final_cost' => 0,
            'wholesale_final_cost' => 0,
        ]);
        $product->company_id = $company->id;
        $product->save();

        // The batch selector must include created batches without extra costs.
        $batchResponse = $this->withHeaders($headers)
            ->getJson('/api/batches')
            ->assertOk();
        $selectedBatch = collect($batchResponse->json())
            ->firstWhere('id', $batch->id);
        $this->assertNotNull($selectedBatch);
        $this->assertSame('created', $selectedBatch['status']);
        $this->assertSame([], $selectedBatch['costs']);

        // Products are visible for inspection and costing even with no costs.
        $productsResponse = $this->withHeaders($headers)
            ->getJson('/api/products/with-costs')
            ->assertOk();
        $matchingProduct = collect($productsResponse->json())
            ->firstWhere('id', $product->id);
        $this->assertNotNull($matchingProduct);
        $this->assertEquals(0, $matchingProduct['unit_cost']);
        $this->assertEquals(25, $matchingProduct['price_shipping']);

        // Cost records are attached to the batch and allocated by quantity.
        Cost::create([
            'amount' => 20,
            'description' => 'Envío y comisiones',
            'batch_id' => $batch->id,
        ]);
        $batchResponse = $this->withHeaders($headers)
            ->getJson('/api/batches')
            ->assertOk();
        $selectedBatch = collect($batchResponse->json())
            ->firstWhere('id', $batch->id);
        $this->assertCount(1, $selectedBatch['costs']);
        $this->assertEquals(20, $selectedBatch['costs'][0]['amount']);

        $productsResponse = $this->withHeaders($headers)
            ->getJson('/api/products/with-costs')
            ->assertOk();
        $matchingProduct = collect($productsResponse->json())
            ->firstWhere('id', $product->id);
        $this->assertEquals(10, $matchingProduct['unit_cost']);
        $this->assertEquals(35, $matchingProduct['price_shipping']);

        $pricePayload = [
            'id' => $product->id,
            'final_cost' => 45,
            'wholesale_final_cost' => 40,
        ];

        $this->withHeaders($headers)
            ->putJson('/api/products/update-final-cost', $pricePayload)
            ->assertStatus(422);

        $batch->status = 'received';
        $batch->save();

        $this->withHeaders($headers)
            ->putJson('/api/products/update-final-cost', $pricePayload)
            ->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'final_cost' => 45,
            'wholesale_final_cost' => 40,
        ]);
    }
}
