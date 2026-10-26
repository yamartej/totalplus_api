<?php

namespace Tests\Feature;

use App\Models\PurchaseOrderProduct;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression of the current authenticated API contract, not the pre-tenancy routes. */
class PurchaseOrderProductTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/purchaseorderproducts';
    protected string $modelClass = PurchaseOrderProduct::class;

    private function order(): PurchaseOrder
    {
        $supplier = Supplier::create(['name' => 'Line supplier '.uniqid(), 'phone' => '123456']);
        $supplier->company_id = $this->qaCompany->id;
        $supplier->save();
        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'tracking_number' => 'QA-'.uniqid(),
            'shipping_cost' => 12.50,
        ]);
        $order->company_id = $this->qaCompany->id;
        $order->save();
        return $order;
    }
    private function product(): Product
    {
        return Product::factory()->create(['company_id' => $this->qaCompany->id]);
    }
    protected function fixture(): Model
    {
        return PurchaseOrderProduct::create([
            'purchase_order_id' => $this->order()->id,
            'product_id' => $this->product()->id, 'price' => 12.50, 'quantity' => 2,
        ]);
    }
    protected function createPayload(): array
    {
        return ['purchase_order_id' => $this->order()->id,
            'product_id' => $this->product()->id, 'price' => 12.50, 'quantity' => 2];
    }
    protected function updatePayload(): array
    {
        return ['price' => 13.25, 'quantity' => 3];
    }
}
