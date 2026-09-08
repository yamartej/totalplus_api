<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
use App\Models\Company;
use App\Models\Cost;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryCompatibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCompatibilityPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function grantProductViewPermission(User $user): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 2E Inventory Compatibility'],
            ['description' => 'Test-only Phase 2E role']
        );

        $permission = Permission::firstOrCreate(
            ['name' => 'products.view'],
            ['description' => 'Phase 2E product view permission']
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
        $this->grantProductViewPermission($user);

        return [
            'Authorization' => 'Bearer ' .
                $user
                    ->createToken('phase-2e-inventory-test')
                    ->plainTextToken,
        ];
    }

    private function context(): array
    {
        $company = Company::create([
            'name' => 'Phase 2E Company',
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Warehouse A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Warehouse B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'quantity' => 50,
        ]);

        $product->company_id = $company->id;
        $product->save();

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        return compact(
            'company',
            'warehouseA',
            'warehouseB',
            'product',
            'user'
        );
    }

    private function otherCompanyContext(): array
    {
        $company = Company::create([
            'name' => 'Phase 2E Other Company',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Other Company Warehouse',
            'description' => 'Other',
            'address' => 'Other',
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'quantity' => 80,
        ]);

        $product->company_id = $company->id;
        $product->save();

        return compact(
            'company',
            'warehouse',
            'product'
        );
    }

    public function test_canonical_stock_is_sum_of_inventory_balances(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $service = app(InventoryCompatibilityService::class);

        $this->assertSame(
            30,
            $service->totalOnHand($ctx['product'])
        );
    }

    public function test_unallocated_quantity_does_not_mutate_legacy_quantity(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 12,
        ]);

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 8,
        ]);

        $service = app(InventoryCompatibilityService::class);

        $this->assertSame(
            20,
            $service->totalOnHand($ctx['product'])
        );

        $this->assertSame(
            30,
            $service->unallocatedQuantity($ctx['product'])
        );

        $this->assertSame(
            50,
            (int) $ctx['product']->fresh()->quantity
        );
    }

    public function test_product_warehouse_quantity_is_not_a_stock_source(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        DB::table('product_warehouse')->insert([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(InventoryCompatibilityService::class);

        $this->assertSame(
            10,
            $service->totalOnHand($ctx['product'])
        );

        $this->assertSame(
            40,
            $service->unallocatedQuantity($ctx['product'])
        );
    }

    public function test_available_products_exposes_multi_warehouse_compatibility_contract(): void
    {
        $ctx = $this->context();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['user']))
            ->getJson('/api/products/available');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $ctx['product']->id,
            'quantity' => 20,
            'legacy_quantity' => 50,
            'inventory_total_quantity' => 30,
            'unallocated_quantity' => 20,
        ]);

        $response->assertJsonFragment([
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $response->assertJsonFragment([
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $this->assertSame(
            50,
            (int) $ctx['product']->fresh()->quantity
        );
    }

    public function test_available_products_excludes_other_company_products_and_stock(): void
    {
        $ctx = $this->context();
        $other = $this->otherCompanyContext();

        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        // Defensive legacy-data scenario: an inventory row for the same
        // product exists in a warehouse belonging to another company.
        Inventory::create([
            'product_id' => $ctx['product']->id,
            'warehouse_id' => $other['warehouse']->id,
            'quantity' => 20,
        ]);

        Inventory::create([
            'product_id' => $other['product']->id,
            'warehouse_id' => $other['warehouse']->id,
            'quantity' => 15,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['user']))
            ->getJson('/api/products/available');

        $response->assertStatus(200);

        $payload = collect($response->json());
        $productIds = $payload->pluck('id')->map(
            fn ($id) => (int) $id
        );

        $this->assertTrue(
            $productIds->contains((int) $ctx['product']->id)
        );

        $this->assertFalse(
            $productIds->contains((int) $other['product']->id)
        );

        $ownProductPayload = $payload->firstWhere(
            'id',
            $ctx['product']->id
        );

        $this->assertSame(
            10,
            (int) $ownProductPayload[
                'inventory_total_quantity'
            ]
        );

        $this->assertSame(
            40,
            (int) $ownProductPayload[
                'unallocated_quantity'
            ]
        );

        $this->assertCount(
            1,
            $ownProductPayload['inventories']
        );

        $this->assertSame(
            $ctx['warehouseA']->id,
            (int) $ownProductPayload[
                'inventories'
            ][0]['warehouse_id']
        );
    }

    public function test_available_products_keeps_legacy_null_company_product_but_scopes_its_stock(): void
    {
        $ctx = $this->context();
        $other = $this->otherCompanyContext();

        $legacyProduct = Product::factory()->create([
            'quantity' => 30,
        ]);

        $legacyProduct->company_id = null;
        $legacyProduct->save();

        Inventory::create([
            'product_id' => $legacyProduct->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 5,
        ]);

        Inventory::create([
            'product_id' => $legacyProduct->id,
            'warehouse_id' => $other['warehouse']->id,
            'quantity' => 7,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($ctx['user']))
            ->getJson('/api/products/available');

        $response->assertStatus(200);

        $payload = collect($response->json());

        $legacyPayload = $payload->firstWhere(
            'id',
            $legacyProduct->id
        );

        $this->assertNotNull($legacyPayload);

        $this->assertSame(
            5,
            (int) $legacyPayload[
                'inventory_total_quantity'
            ]
        );

        $this->assertSame(
            25,
            (int) $legacyPayload[
                'unallocated_quantity'
            ]
        );

        $this->assertCount(
            1,
            $legacyPayload['inventories']
        );

        $this->assertSame(
            $ctx['warehouseA']->id,
            (int) $legacyPayload[
                'inventories'
            ][0]['warehouse_id']
        );
    }

    public function test_products_with_costs_is_scoped_and_uses_tenant_batch_denominator(): void
    {
        $ctx = $this->context();
        $other = $this->otherCompanyContext();

        $batch = Batch::create([
            'name' => 'Phase 2E Shared Batch',
            'description' => 'Tenant denominator test',
            'quantity' => '100',
        ]);

        $batch->company_id = $ctx['company']->id;
        $batch->save();

        Cost::create([
            'amount' => 100,
            'description' => 'Freight',
            'batch_id' => $batch->id,
        ]);

        $ctx['product']->batch_id = $batch->id;
        $ctx['product']->quantity = 10;
        $ctx['product']->price = 5;
        $ctx['product']->save();

        $other['product']->batch_id = $batch->id;
        $other['product']->quantity = 90;
        $other['product']->price = 5;
        $other['product']->save();

        $response = $this
            ->withHeaders($this->authHeaders($ctx['user']))
            ->getJson('/api/products/with-costs');

        $response->assertStatus(200);

        $payload = collect($response->json());
        $productIds = $payload->pluck('id')->map(
            fn ($id) => (int) $id
        );

        $this->assertTrue(
            $productIds->contains((int) $ctx['product']->id)
        );

        $this->assertFalse(
            $productIds->contains((int) $other['product']->id)
        );

        $ownProductPayload = $payload->firstWhere(
            'id',
            $ctx['product']->id
        );

        // 100 cost / 10 units from this tenant = 10.
        // Without tenant scoping, the other company's 90 units would
        // incorrectly make this 1.
        $this->assertSame(
            10.0,
            (float) $ownProductPayload['unit_cost']
        );

        $this->assertSame(
            15.0,
            (float) $ownProductPayload['price_shipping']
        );
    }
}
