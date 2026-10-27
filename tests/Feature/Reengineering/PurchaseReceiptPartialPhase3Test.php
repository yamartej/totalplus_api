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
use App\Models\PurchaseReceipt;
use App\Models\Roles;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseReceiptPartialPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function setupOrder(): array
    {
        $company = Company::create(['name' => 'Partial Receipt Company']);
        $other = Company::create(['name' => 'Foreign Receipt Company']);

        $warehouse = Warehouse::create([
            'name' => 'Receiving', 'description' => 'QA',
            'address' => 'QA', 'company_id' => $company->id,
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $role = Roles::create(['name' => 'Partial Receiving Operator']);
        $permission = Permission::firstOrCreate(
            ['name' => 'purchaseorders.update'],
            ['description' => 'Test permission']
        );
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $this->actingAs($user, 'sanctum');

        $supplier = Supplier::create([
            'name' => 'Receiving Supplier', 'phone' => '111111',
        ]);
        $supplier->company_id = $company->id;
        $supplier->save();

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'tracking_number' => 'PARTIAL-001',
            'shipping_cost' => 0,
        ]);
        $order->company_id = $company->id;
        $order->save();

        $category = Category::factory()->create();
        $productA = Product::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $productB = Product::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);
        $foreignProduct = Product::factory()->create([
            'company_id' => $other->id,
            'category_id' => $category->id,
        ]);

        $lineA = PurchaseOrderProduct::create([
            'purchase_order_id' => $order->id,
            'product_id' => $productA->id,
            'quantity' => 5, 'price' => 10,
        ]);
        $lineB = PurchaseOrderProduct::create([
            'purchase_order_id' => $order->id,
            'product_id' => $productB->id,
            'quantity' => 3, 'price' => 10,
        ]);

        return compact(
            'company', 'other', 'warehouse', 'user', 'order',
            'productA', 'productB', 'foreignProduct', 'lineA', 'lineB'
        );
    }

    private function endpoint(PurchaseOrder $order): string
    {
        return '/api/purchaseorders/' . $order->id . '/receive';
    }

    private function partialPayload($ctx, string $key, array $lines): array
    {
        return [
            'warehouse_id' => $ctx['warehouse']->id,
            'receipt_key' => $key,
            'lines' => $lines,
        ];
    }

    private function balance(Product $product, Warehouse $warehouse): int
    {
        return (int) Inventory::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity');
    }

    public function test_partial_receipts_across_deliveries_preserve_stock_and_kardex(): void
    {
        $ctx = $this->setupOrder();
        $url = $this->endpoint($ctx['order']);

        $first = $this->postJson($url, $this->partialPayload(
            $ctx, 'delivery-001',
            [['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 2]]
        ))->assertCreated();

        $first->assertJsonCount(1, 'lines');
        $this->assertSame(2, $this->balance($ctx['productA'], $ctx['warehouse']));
        $this->assertSame(0, $this->balance($ctx['productB'], $ctx['warehouse']));

        $this->postJson($url, $this->partialPayload(
            $ctx, 'delivery-002',
            [
                ['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 3],
                ['purchase_order_product_id' => $ctx['lineB']->id, 'quantity' => 3],
            ]
        ))->assertCreated()->assertJsonCount(2, 'lines');

        $this->assertSame(5, $this->balance($ctx['productA'], $ctx['warehouse']));
        $this->assertSame(3, $this->balance($ctx['productB'], $ctx['warehouse']));
        $this->assertSame(2, PurchaseReceipt::count());
        $this->assertSame(3, DB::table('purchase_receipt_lines')->count());
        $this->assertSame(
            3,
            InventoryMovement::query()->where('reference_type', 'purchase_receipt')->count()
        );

        $this->postJson($url, [
            'warehouse_id' => $ctx['warehouse']->id,
        ])->assertStatus(409);
    }

    public function test_replay_of_partial_delivery_key_cannot_post_stock_twice(): void
    {
        $ctx = $this->setupOrder();
        $url = $this->endpoint($ctx['order']);
        $data = $this->partialPayload(
            $ctx, 'delivery-repeat-001',
            [['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 2]]
        );

        $this->postJson($url, $data)->assertCreated();
        $this->postJson($url, $data)->assertStatus(409);

        $this->assertSame(2, $this->balance($ctx['productA'], $ctx['warehouse']));
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(
            1,
            InventoryMovement::query()->where('reference_type', 'purchase_receipt')->count()
        );
    }

    public function test_over_receiving_rejects_entire_request_without_movements(): void
    {
        $ctx = $this->setupOrder();
        $url = $this->endpoint($ctx['order']);

        $this->postJson($url, $this->partialPayload(
            $ctx, 'delivery-good',
            [['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 4]]
        ))->assertCreated();

        $beforeMovements = InventoryMovement::count();

        $this->postJson($url, $this->partialPayload(
            $ctx, 'delivery-over',
            [
                ['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 2],
                ['purchase_order_product_id' => $ctx['lineB']->id, 'quantity' => 1],
            ]
        ))->assertStatus(422);

        $this->assertSame(4, $this->balance($ctx['productA'], $ctx['warehouse']));
        $this->assertSame(0, $this->balance($ctx['productB'], $ctx['warehouse']));
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame($beforeMovements, InventoryMovement::count());
    }

    public function test_foreign_order_line_and_duplicate_line_are_rejected(): void
    {
        $ctx = $this->setupOrder();
        $otherOrder = PurchaseOrder::create([
            'supplier_id' => $ctx['order']->supplier_id,
            'tracking_number' => 'PARTIAL-OTHER-002',
            'shipping_cost' => 0,
        ]);
        $otherOrder->company_id = $ctx['company']->id;
        $otherOrder->save();
        $otherLine = PurchaseOrderProduct::create([
            'purchase_order_id' => $otherOrder->id,
            'product_id' => $ctx['productA']->id,
            'quantity' => 4, 'price' => 10,
        ]);

        $this->postJson($this->endpoint($ctx['order']), $this->partialPayload(
            $ctx, 'wrong-order',
            [['purchase_order_product_id' => $otherLine->id, 'quantity' => 1]]
        ))->assertStatus(422);

        $same = ['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 1];
        $this->postJson($this->endpoint($ctx['order']), $this->partialPayload(
            $ctx, 'duplicate-line',
            [$same, $same]
        ))->assertStatus(422);

        $this->assertSame(0, PurchaseReceipt::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_partial_receipt_requires_receipt_key(): void
    {
        $ctx = $this->setupOrder();
        $this->postJson($this->endpoint($ctx['order']), [
            'warehouse_id' => $ctx['warehouse']->id,
            'lines' => [
                ['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('receipt_key');

        $this->assertSame(0, PurchaseReceipt::count());
    }

    public function test_legacy_full_receipt_still_rejects_partial_retry(): void
    {
        $ctx = $this->setupOrder();
        $this->postJson($this->endpoint($ctx['order']), [
            'warehouse_id' => $ctx['warehouse']->id,
        ])->assertCreated();

        $this->postJson($this->endpoint($ctx['order']), $this->partialPayload(
            $ctx, 'after-full',
            [['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 1]]
        ))->assertStatus(409);

        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(5, $this->balance($ctx['productA'], $ctx['warehouse']));
        $this->assertSame(3, $this->balance($ctx['productB'], $ctx['warehouse']));
    }

    public function test_existing_receipt_without_lines_is_treated_as_legacy_complete(): void
    {
        $ctx = $this->setupOrder();
        $receipt = new PurchaseReceipt([
            'purchase_order_id' => $ctx['order']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'received_by_user_id' => $ctx['user']->id,
            'received_at' => now(),
        ]);
        $receipt->company_id = $ctx['company']->id;
        $receipt->save();

        $this->postJson($this->endpoint($ctx['order']), $this->partialPayload(
            $ctx, 'old-order',
            [['purchase_order_product_id' => $ctx['lineA']->id, 'quantity' => 1]]
        ))->assertStatus(409);

        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(0, InventoryMovement::count());
    }
}
