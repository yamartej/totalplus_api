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

class PurchaseOrderProductTenancyPhase5Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(User $user, array $names): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 5 Purchase Order Product Tenancy'],
            ['description' => 'Phase 5 purchase order product tenancy test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 5 purchase order product tenancy permission']
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
                    ->createToken('phase-5-purchase-order-product-tenancy')
                    ->plainTextToken,
        ];
    }

    private function makeSupplier(
        string $name,
        ?int $companyId
    ): Supplier {
        $supplier = Supplier::create([
            'name' => $name,
            'phone' => '555-0100',
        ]);

        $supplier->company_id = $companyId;
        $supplier->save();

        return $supplier;
    }

    private function makePurchaseOrder(
        int $supplierId,
        ?int $companyId,
        string $tracking
    ): PurchaseOrder {
        $order = PurchaseOrder::create([
            'supplier_id' => $supplierId,
            'tracking_number' => $tracking,
            'shipping_cost' => 10,
        ]);

        $order->company_id = $companyId;
        $order->save();

        return $order;
    }

    private function makeProduct(
        string $name,
        ?int $companyId,
        int $categoryId
    ): Product {
        $product = Product::create([
            'name' => $name,
            'description' => 'Phase 5 purchase line fixture',
            'price' => 5.00,
            'category_id' => $categoryId,
            'quantity' => 10,
            'batch_id' => null,
            'final_cost' => 10.00,
            'wholesale_final_cost' => 8.00,
        ]);

        $product->company_id = $companyId;
        $product->save();

        return $product;
    }

    private function makeLine(
        int $orderId,
        int $productId,
        float $price = 3.50,
        int $quantity = 2
    ): PurchaseOrderProduct {
        return PurchaseOrderProduct::create([
            'purchase_order_id' => $orderId,
            'product_id' => $productId,
            'price' => $price,
            'quantity' => $quantity,
        ]);
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 5 Line Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 5 Line Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $globalUser = User::factory()->create([
            'company_id' => null,
        ]);

        $category = Category::factory()->create();

        $supplierA = $this->makeSupplier(
            'Line Supplier A',
            (int) $companyA->id
        );

        $supplierB = $this->makeSupplier(
            'Line Supplier B',
            (int) $companyB->id
        );

        $legacySupplier = $this->makeSupplier(
            'Line Legacy Supplier',
            null
        );

        $orderA = $this->makePurchaseOrder(
            (int) $supplierA->id,
            (int) $companyA->id,
            'LINE-A-001'
        );

        $orderB = $this->makePurchaseOrder(
            (int) $supplierB->id,
            (int) $companyB->id,
            'LINE-B-001'
        );

        $legacyOrder = $this->makePurchaseOrder(
            (int) $legacySupplier->id,
            null,
            'LINE-LEG-001'
        );

        $productA = $this->makeProduct(
            'Line Product A',
            (int) $companyA->id,
            (int) $category->id
        );

        $productB = $this->makeProduct(
            'Line Product B',
            (int) $companyB->id,
            (int) $category->id
        );

        $legacyProduct = $this->makeProduct(
            'Line Legacy Product',
            null,
            (int) $category->id
        );

        $lineA = $this->makeLine(
            (int) $orderA->id,
            (int) $productA->id
        );

        $lineB = $this->makeLine(
            (int) $orderB->id,
            (int) $productB->id
        );

        $legacyLine = $this->makeLine(
            (int) $legacyOrder->id,
            (int) $legacyProduct->id
        );

        return compact(
            'companyA',
            'companyB',
            'userA',
            'globalUser',
            'orderA',
            'orderB',
            'legacyOrder',
            'productA',
            'productB',
            'legacyProduct',
            'lineA',
            'lineB',
            'legacyLine'
        );
    }

    public function test_line_index_excludes_other_company_and_keeps_legacy_visible(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    [
                        'products.view',
                        'purchaseorderproducts.view',
                    ]
                )
            )
            ->getJson('/api/purchaseorderproducts');

        $response->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue(
            $ids->contains((int) $ctx['lineA']->id)
        );

        $this->assertTrue(
            $ids->contains((int) $ctx['legacyLine']->id)
        );

        $this->assertFalse(
            $ids->contains((int) $ctx['lineB']->id)
        );
    }

    public function test_line_can_be_created_for_owned_order_and_owned_product(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorderproducts.create']
                )
            )
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['orderA']->id,
                'product_id' => $ctx['productA']->id,
                'price' => 4.25,
                'quantity' => 3,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas(
            'purchase_order_products',
            [
                'purchase_order_id' => $ctx['orderA']->id,
                'product_id' => $ctx['productA']->id,
                'quantity' => 3,
            ]
        );
    }

    public function test_line_can_use_legacy_product_on_owned_order_for_compatibility(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorderproducts.create']
                )
            )
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['orderA']->id,
                'product_id' => $ctx['legacyProduct']->id,
                'price' => 4.50,
                'quantity' => 2,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas(
            'purchase_order_products',
            [
                'purchase_order_id' => $ctx['orderA']->id,
                'product_id' => $ctx['legacyProduct']->id,
                'quantity' => 2,
            ]
        );
    }

    public function test_line_cannot_be_created_for_other_company_order(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorderproducts.create']
                )
            )
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['orderB']->id,
                'product_id' => $ctx['productA']->id,
                'price' => 4.25,
                'quantity' => 3,
            ]);

        $response->assertStatus(404);
    }

    public function test_line_cannot_use_product_from_other_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorderproducts.create']
                )
            )
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['orderA']->id,
                'product_id' => $ctx['productB']->id,
                'price' => 4.25,
                'quantity' => 3,
            ]);

        $response->assertStatus(404);
    }

    public function test_other_company_line_cannot_be_read_by_id(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorderproducts.view']
                )
            )
            ->getJson(
                '/api/purchaseorderproducts/' . $ctx['lineB']->id
            );

        $response->assertStatus(404);
    }

    public function test_other_company_line_cannot_be_updated(): void
    {
        $ctx = $this->context();

        $original = $ctx['lineB']->fresh();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorderproducts.update']
                )
            )
            ->putJson(
                '/api/purchaseorderproducts/' . $ctx['lineB']->id,
                [
                    'price' => 99.00,
                    'quantity' => 99,
                ]
            );

        $response->assertStatus(404);

        $fresh = $ctx['lineB']->fresh();

        $this->assertSame(
            (float) $original->price,
            (float) $fresh->price
        );

        $this->assertSame(
            (int) $original->quantity,
            (int) $fresh->quantity
        );
    }

    public function test_legacy_line_is_read_only_for_company_tenant(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorderproducts.delete']
                )
            )
            ->deleteJson(
                '/api/purchaseorderproducts/' . $ctx['legacyLine']->id
            );

        $response->assertStatus(404);

        $this->assertNotNull(
            PurchaseOrderProduct::find($ctx['legacyLine']->id)
        );
    }

    public function test_global_user_cannot_create_line_without_company_context(): void
    {
        $ctx = $this->context();

        $this->grantPermissions(
            $ctx['globalUser'],
            [
                'tenant.cross_company',
                'purchaseorderproducts.create',
            ]
        );

        $headers = [
            'Authorization' => 'Bearer '
                . $ctx['globalUser']
                    ->createToken('phase-5-global-purchase-line')
                    ->plainTextToken,
        ];

        $response = $this
            ->withHeaders($headers)
            ->postJson('/api/purchaseorderproducts', [
                'purchase_order_id' => $ctx['orderA']->id,
                'product_id' => $ctx['productA']->id,
                'price' => 4.25,
                'quantity' => 3,
            ]);

        $response->assertStatus(422);
    }
}
