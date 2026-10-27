<?php

namespace Tests\Feature\Reengineering;

use App\Models\Category;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderProduct;
use App\Models\Roles;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderAtomicPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $company = Company::create(['name' => 'Purchase operations A']);
        $other = Company::create(['name' => 'Purchase operations B']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $role = Roles::create(['name' => 'Purchase operations test role']);
        $permission = Permission::firstOrCreate(
            ['name' => 'purchaseorders.create'],
            ['description' => 'Can create orders']
        );
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this->actingAs($user, 'sanctum');

        $supplier = Supplier::create(['name' => 'Supplier A', 'phone' => '55501']);
        $supplier->company_id = $company->id;
        $supplier->save();

        $foreignSupplier = Supplier::create([
            'name' => 'Supplier B', 'phone' => '55502',
        ]);
        $foreignSupplier->company_id = $other->id;
        $foreignSupplier->save();

        $category = Category::factory()->create();

        $productA = Product::factory()->create([
            'company_id' => $company->id, 'category_id' => $category->id,
        ]);
        $productB = Product::factory()->create([
            'company_id' => $company->id, 'category_id' => $category->id,
        ]);
        $foreignProduct = Product::factory()->create([
            'company_id' => $other->id, 'category_id' => $category->id,
        ]);

        return compact(
            'company', 'other', 'user', 'supplier', 'foreignSupplier',
            'productA', 'productB', 'foreignProduct'
        );
    }

    private function payload(array $ctx): array
    {
        return [
            'supplier_id' => $ctx['supplier']->id,
            'shipping_cost' => 11.50,
            'tracking_number' => 'FULL-ORDER-001',
            'lines' => [
                ['product_id' => $ctx['productA']->id, 'quantity' => 5, 'price' => 12.25],
                ['product_id' => $ctx['productB']->id, 'quantity' => 2, 'price' => 9],
            ],
        ];
    }

    public function test_order_and_both_lines_are_created_in_one_transaction(): void
    {
        $ctx = $this->context();
        $response = $this->postJson(
            '/api/purchaseorders/with-lines',
            $this->payload($ctx)
        )->assertCreated()->assertJsonCount(2, 'lines');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $response->json('id'),
            'company_id' => $ctx['company']->id,
            'supplier_id' => $ctx['supplier']->id,
        ]);
        $this->assertSame(1, PurchaseOrder::count());
        $this->assertSame(2, PurchaseOrderProduct::count());
    }

    public function test_foreign_supplier_cannot_create_order_or_lines(): void
    {
        $ctx = $this->context();
        $payload = $this->payload($ctx);
        $payload['supplier_id'] = $ctx['foreignSupplier']->id;

        $this->postJson('/api/purchaseorders/with-lines', $payload)
            ->assertStatus(404);

        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, PurchaseOrderProduct::count());
    }

    public function test_foreign_product_rejects_entire_order(): void
    {
        $ctx = $this->context();
        $payload = $this->payload($ctx);
        $payload['lines'][1]['product_id'] = $ctx['foreignProduct']->id;

        $this->postJson('/api/purchaseorders/with-lines', $payload)
            ->assertStatus(404);

        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, PurchaseOrderProduct::count());
    }

    public function test_duplicate_lines_and_zero_quantity_fail_validation(): void
    {
        $ctx = $this->context();
        $payload = $this->payload($ctx);
        $payload['lines'][1]['product_id'] = $ctx['productA']->id;

        $this->postJson('/api/purchaseorders/with-lines', $payload)
            ->assertStatus(422);

        $payload = $this->payload($ctx);
        $payload['lines'][0]['quantity'] = 0;

        $this->postJson('/api/purchaseorders/with-lines', $payload)
            ->assertStatus(422);

        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, PurchaseOrderProduct::count());
    }

    public function test_unauthorized_user_cannot_create_purchase_order(): void
    {
        $ctx = $this->context();

        $otherUser = User::factory()->create([
            'company_id' => $ctx['company']->id,
        ]);
        $this->actingAs($otherUser, 'sanctum');

        $this->postJson(
            '/api/purchaseorders/with-lines',
            $this->payload($ctx)
        )->assertStatus(403);

        $this->assertSame(0, PurchaseOrder::count());
    }
}
