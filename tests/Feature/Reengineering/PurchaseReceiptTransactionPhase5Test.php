<?php

namespace Tests\Feature\Reengineering;

use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\InventoryMovement;
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

class PurchaseReceiptTransactionPhase5Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(User $user, array $names): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 5 Purchase Receipt'],
            ['description' => 'Phase 5 purchase receipt test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 5 purchase receipt permission']
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
                    ->createToken('phase-5-purchase-receipt')
                    ->plainTextToken,
        ];
    }

    private function makeSupplier(
        string $name,
        ?int $companyId
    ): Supplier {
        $supplier = Supplier::create([
            'name' => $name,
            'phone' => '555-0150',
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
        int $categoryId,
        int $legacyQuantity
    ): Product {
        $product = Product::create([
            'name' => $name,
            'description' => 'Phase 5 receipt fixture',
            'price' => 5.00,
            'category_id' => $categoryId,
            'quantity' => $legacyQuantity,
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
        int $quantity,
        float $price = 3.50
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
            'name' => 'Phase 5 Receipt Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 5 Receipt Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $globalUser = User::factory()->create([
            'company_id' => null,
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Receipt Warehouse A',
            'description' => 'Company A receipt warehouse',
            'address' => 'A',
            'company_id' => $companyA->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Receipt Warehouse B',
            'description' => 'Company B receipt warehouse',
            'address' => 'B',
            'company_id' => $companyB->id,
        ]);

        $category = Category::factory()->create();

        $supplierA = $this->makeSupplier(
            'Receipt Supplier A',
            (int) $companyA->id
        );

        $supplierB = $this->makeSupplier(
            'Receipt Supplier B',
            (int) $companyB->id
        );

        $legacySupplier = $this->makeSupplier(
            'Receipt Legacy Supplier',
            null
        );

        $orderA = $this->makePurchaseOrder(
            (int) $supplierA->id,
            (int) $companyA->id,
            'RECEIPT-A-001'
        );

        $orderB = $this->makePurchaseOrder(
            (int) $supplierB->id,
            (int) $companyB->id,
            'RECEIPT-B-001'
        );

        $legacyOrder = $this->makePurchaseOrder(
            (int) $legacySupplier->id,
            null,
            'RECEIPT-LEG-001'
        );

        $productA = $this->makeProduct(
            'Receipt Product A',
            (int) $companyA->id,
            (int) $category->id,
            40
        );

        $productB = $this->makeProduct(
            'Receipt Product B',
            (int) $companyB->id,
            (int) $category->id,
            30
        );

        $legacyProduct = $this->makeProduct(
            'Receipt Legacy Product',
            null,
            (int) $category->id,
            20
        );

        $this->makeLine(
            (int) $orderA->id,
            (int) $productA->id,
            3,
            4.25
        );

        $this->makeLine(
            (int) $orderA->id,
            (int) $legacyProduct->id,
            2,
            3.75
        );

        $this->makeLine(
            (int) $orderB->id,
            (int) $productB->id,
            7,
            5.50
        );

        $this->makeLine(
            (int) $legacyOrder->id,
            (int) $legacyProduct->id,
            4,
            3.25
        );

        Inventory::create([
            'product_id' => $productA->id,
            'warehouse_id' => $warehouseA->id,
            'quantity' => 5,
        ]);

        return compact(
            'companyA',
            'companyB',
            'userA',
            'globalUser',
            'warehouseA',
            'warehouseB',
            'supplierA',
            'supplierB',
            'legacySupplier',
            'orderA',
            'orderB',
            'legacyOrder',
            'productA',
            'productB',
            'legacyProduct'
        );
    }

    public function test_owned_order_receipt_posts_all_lines_to_exact_warehouse_and_kardex(): void
    {
        $ctx = $this->context();

        $productAQuantityBefore = (int) $ctx['productA']->quantity;
        $legacyProductQuantityBefore = (int) $ctx['legacyProduct']->quantity;

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.update']
                )
            )
            ->postJson(
                '/api/purchaseorders/' . $ctx['orderA']->id . '/receive',
                [
                    'warehouse_id' => $ctx['warehouseA']->id,
                ]
            );

        $response->assertStatus(201);

        $receipt = DB::table('purchase_receipts')
            ->where('purchase_order_id', $ctx['orderA']->id)
            ->first();

        $this->assertNotNull($receipt);
        $this->assertSame(
            (int) $ctx['companyA']->id,
            (int) $receipt->company_id
        );
        $this->assertSame(
            (int) $ctx['warehouseA']->id,
            (int) $receipt->warehouse_id
        );
        $this->assertSame(
            (int) $ctx['userA']->id,
            (int) $receipt->received_by_user_id
        );
        $this->assertNotNull($receipt->received_at);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['productA']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 8,
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $ctx['legacyProduct']->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 2,
        ]);

        $movements = InventoryMovement::query()
            ->where('reference_type', 'purchase_receipt')
            ->where('reference_id', $receipt->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movements);

        foreach ($movements as $movement) {
            $this->assertSame(
                InventoryMovement::TYPE_RECEIVE,
                $movement->type
            );
            $this->assertSame(
                (int) $ctx['companyA']->id,
                (int) $movement->company_id
            );
            $this->assertSame(
                (int) $ctx['warehouseA']->id,
                (int) $movement->warehouse_id
            );
            $this->assertSame(
                (int) $ctx['userA']->id,
                (int) $movement->user_id
            );
        }

        $this->assertSame(
            $productAQuantityBefore,
            (int) $ctx['productA']->fresh()->quantity,
            'Receipt must not mutate legacy products.quantity.'
        );

        $this->assertSame(
            $legacyProductQuantityBefore,
            (int) $ctx['legacyProduct']->fresh()->quantity,
            'Receipt must not mutate legacy products.quantity.'
        );
    }

    public function test_same_purchase_order_cannot_be_received_twice(): void
    {
        $ctx = $this->context();

        $headers = $this->authHeaders(
            $ctx['userA'],
            ['purchaseorders.update']
        );

        $url = '/api/purchaseorders/'
            . $ctx['orderA']->id
            . '/receive';

        $first = $this
            ->withHeaders($headers)
            ->postJson($url, [
                'warehouse_id' => $ctx['warehouseA']->id,
            ]);

        $first->assertStatus(201);

        $inventoryAfterFirst = Inventory::query()
            ->where('product_id', $ctx['productA']->id)
            ->where('warehouse_id', $ctx['warehouseA']->id)
            ->value('quantity');

        $movementsAfterFirst = InventoryMovement::query()
            ->where('reference_type', 'purchase_receipt')
            ->count();

        $second = $this
            ->withHeaders($headers)
            ->postJson($url, [
                'warehouse_id' => $ctx['warehouseA']->id,
            ]);

        $second->assertStatus(409);

        $this->assertSame(
            1,
            DB::table('purchase_receipts')
                ->where('purchase_order_id', $ctx['orderA']->id)
                ->count()
        );

        $this->assertSame(
            (int) $inventoryAfterFirst,
            (int) Inventory::query()
                ->where('product_id', $ctx['productA']->id)
                ->where('warehouse_id', $ctx['warehouseA']->id)
                ->value('quantity')
        );

        $this->assertSame(
            $movementsAfterFirst,
            InventoryMovement::query()
                ->where('reference_type', 'purchase_receipt')
                ->count()
        );
    }

    public function test_receipt_rejects_warehouse_from_other_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.update']
                )
            )
            ->postJson(
                '/api/purchaseorders/' . $ctx['orderA']->id . '/receive',
                [
                    'warehouse_id' => $ctx['warehouseB']->id,
                ]
            );

        $response->assertStatus(404);

        $this->assertSame(
            0,
            DB::table('purchase_receipts')->count()
        );
    }

    public function test_receipt_rejects_purchase_order_from_other_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.update']
                )
            )
            ->postJson(
                '/api/purchaseorders/' . $ctx['orderB']->id . '/receive',
                [
                    'warehouse_id' => $ctx['warehouseA']->id,
                ]
            );

        $response->assertStatus(404);

        $this->assertSame(
            0,
            DB::table('purchase_receipts')->count()
        );
    }

    public function test_legacy_purchase_order_is_read_only_and_cannot_be_received(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.update']
                )
            )
            ->postJson(
                '/api/purchaseorders/' . $ctx['legacyOrder']->id . '/receive',
                [
                    'warehouse_id' => $ctx['warehouseA']->id,
                ]
            );

        $response->assertStatus(404);

        $this->assertSame(
            0,
            DB::table('purchase_receipts')->count()
        );
    }

    public function test_global_user_requires_explicit_company_context_to_receive(): void
    {
        $ctx = $this->context();

        $this->grantPermissions(
            $ctx['globalUser'],
            [
                'tenant.cross_company',
                'purchaseorders.update',
            ]
        );

        $headers = [
            'Authorization' => 'Bearer '
                . $ctx['globalUser']
                    ->createToken('phase-5-global-receipt')
                    ->plainTextToken,
        ];

        $response = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/purchaseorders/' . $ctx['orderA']->id . '/receive',
                [
                    'warehouse_id' => $ctx['warehouseA']->id,
                ]
            );

        $response->assertStatus(422);

        $this->assertSame(
            0,
            DB::table('purchase_receipts')->count()
        );
    }

    public function test_invalid_foreign_product_line_rolls_back_entire_receipt(): void
    {
        $ctx = $this->context();

        $order = $this->makePurchaseOrder(
            (int) $ctx['supplierA']->id,
            (int) $ctx['companyA']->id,
            'RECEIPT-ROLLBACK-001'
        );

        $this->makeLine(
            (int) $order->id,
            (int) $ctx['productA']->id,
            2,
            4.00
        );

        $this->makeLine(
            (int) $order->id,
            (int) $ctx['productB']->id,
            3,
            4.00
        );

        $stockBefore = (int) Inventory::query()
            ->where('product_id', $ctx['productA']->id)
            ->where('warehouse_id', $ctx['warehouseA']->id)
            ->value('quantity');

        $movementCountBefore = InventoryMovement::count();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.update']
                )
            )
            ->postJson(
                '/api/purchaseorders/' . $order->id . '/receive',
                [
                    'warehouse_id' => $ctx['warehouseA']->id,
                ]
            );

        $response->assertStatus(422);

        $this->assertSame(
            0,
            DB::table('purchase_receipts')
                ->where('purchase_order_id', $order->id)
                ->count()
        );

        $this->assertSame(
            $stockBefore,
            (int) Inventory::query()
                ->where('product_id', $ctx['productA']->id)
                ->where('warehouse_id', $ctx['warehouseA']->id)
                ->value('quantity')
        );

        $this->assertSame(
            $movementCountBefore,
            InventoryMovement::count()
        );
    }

    public function test_empty_purchase_order_cannot_be_received(): void
    {
        $ctx = $this->context();

        $emptyOrder = $this->makePurchaseOrder(
            (int) $ctx['supplierA']->id,
            (int) $ctx['companyA']->id,
            'RECEIPT-EMPTY-001'
        );

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.update']
                )
            )
            ->postJson(
                '/api/purchaseorders/' . $emptyOrder->id . '/receive',
                [
                    'warehouse_id' => $ctx['warehouseA']->id,
                ]
            );

        $response->assertStatus(422);

        $this->assertSame(
            0,
            DB::table('purchase_receipts')
                ->where('purchase_order_id', $emptyOrder->id)
                ->count()
        );
    }
}
