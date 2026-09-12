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
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseReceiptImmutabilityPhase5Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(User $user, array $names): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 5 Receipt Immutability'],
            ['description' => 'Phase 5 receipt immutability test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 5 receipt immutability permission']
                )->id;
            })
            ->all();

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function authHeaders(User $user, array $permissions): array
    {
        $this->grantPermissions($user, $permissions);

        return [
            'Authorization' => 'Bearer '
                . $user
                    ->createToken('phase-5-receipt-immutability')
                    ->plainTextToken,
        ];
    }

    private function context(): array
    {
        $company = Company::create([
            'name' => 'Phase 5 Immutable Company',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Immutable Warehouse',
            'description' => 'Phase 5 receipt immutability warehouse',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $supplier = Supplier::create([
            'name' => 'Immutable Supplier',
            'phone' => '555-0199',
        ]);
        $supplier->company_id = $company->id;
        $supplier->save();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'tracking_number' => 'IMM-001',
            'shipping_cost' => 10,
        ]);
        $order->company_id = $company->id;
        $order->save();

        $openOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'tracking_number' => 'OPEN-001',
            'shipping_cost' => 12,
        ]);
        $openOrder->company_id = $company->id;
        $openOrder->save();

        $category = Category::factory()->create();

        $product = Product::create([
            'name' => 'Immutable Product',
            'description' => 'Phase 5 immutable fixture',
            'price' => 5.00,
            'category_id' => $category->id,
            'quantity' => 10,
            'batch_id' => null,
            'final_cost' => 10.00,
            'wholesale_final_cost' => 8.00,
        ]);
        $product->company_id = $company->id;
        $product->save();

        $line = PurchaseOrderProduct::create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'price' => 4.25,
            'quantity' => 3,
        ]);

        DB::table('purchase_receipts')->insert([
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'received_by_user_id' => $user->id,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact(
            'company',
            'user',
            'warehouse',
            'supplier',
            'order',
            'openOrder',
            'product',
            'line'
        );
    }

    public function test_received_order_cannot_accept_new_line(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['purchaseorderproducts.create']
                )
            )
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['order']->id,
                'product_id' => $ctx['product']->id,
                'price' => 4.50,
                'quantity' => 2,
            ]);

        $response->assertStatus(409);

        $this->assertSame(
            1,
            PurchaseOrderProduct::where(
                'purchase_order_id',
                $ctx['order']->id
            )->count()
        );
    }

    public function test_received_order_line_cannot_be_updated(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['purchaseorderproducts.update']
                )
            )
            ->putJson(
                '/api/purchaseorderproducts/' . $ctx['line']->id,
                [
                    'price' => 99.00,
                    'quantity' => 99,
                ]
            );

        $response->assertStatus(409);

        $fresh = $ctx['line']->fresh();

        $this->assertSame(4.25, (float) $fresh->price);
        $this->assertSame(3, (int) $fresh->quantity);
    }

    public function test_received_order_line_cannot_be_deleted(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['purchaseorderproducts.delete']
                )
            )
            ->deleteJson(
                '/api/purchaseorderproducts/' . $ctx['line']->id
            );

        $response->assertStatus(409);

        $this->assertNotNull(
            PurchaseOrderProduct::find($ctx['line']->id)
        );
    }

    public function test_received_order_header_cannot_be_updated(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['purchaseorders.update']
                )
            )
            ->putJson(
                '/api/purchaseorders/' . $ctx['order']->id,
                [
                    'shipping_cost' => 999,
                    'tracking_number' => 'CHANGED',
                ]
            );

        $response->assertStatus(409);

        $fresh = $ctx['order']->fresh();

        $this->assertSame(10.0, (float) $fresh->shipping_cost);
        $this->assertSame('IMM-001', $fresh->tracking_number);
    }

    public function test_received_order_cannot_be_deleted(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['purchaseorders.delete']
                )
            )
            ->deleteJson(
                '/api/purchaseorders/' . $ctx['order']->id
            );

        $response->assertStatus(409);

        $this->assertNotNull(
            PurchaseOrder::find($ctx['order']->id)
        );
    }

    public function test_supplier_with_received_order_cannot_be_deleted(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['suppliers.delete']
                )
            )
            ->deleteJson(
                '/api/suppliers/' . $ctx['supplier']->id
            );

        $response->assertStatus(409);

        $this->assertNotNull(
            Supplier::find($ctx['supplier']->id)
        );
        $this->assertNotNull(
            PurchaseOrder::find($ctx['order']->id)
        );
    }

    public function test_purchase_line_quantity_must_be_positive_integer(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['purchaseorderproducts.create']
                )
            )
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['openOrder']->id,
                'product_id' => $ctx['product']->id,
                'price' => 4.50,
                'quantity' => 0,
            ]);

        $response->assertStatus(422);

        $this->assertSame(
            0,
            PurchaseOrderProduct::where(
                'purchase_order_id',
                $ctx['openOrder']->id
            )->count()
        );
    }

    public function test_unreceived_order_remains_writable(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['user'],
                    ['purchaseorderproducts.create']
                )
            )
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['openOrder']->id,
                'product_id' => $ctx['product']->id,
                'price' => 4.50,
                'quantity' => 2,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas(
            'purchase_order_products',
            [
                'purchase_order_id' => $ctx['openOrder']->id,
                'product_id' => $ctx['product']->id,
                'quantity' => 2,
            ]
        );
    }
}
