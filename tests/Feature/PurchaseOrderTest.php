<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression of the current authenticated API contract, not the pre-tenancy routes. */
class PurchaseOrderTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/purchaseorders';
    protected string $modelClass = PurchaseOrder::class;

    private function supplier(): Supplier
    {
        $supplier = Supplier::create(['name' => 'PO Supplier '.uniqid(), 'phone' => '1234567']);
        $supplier->company_id = $this->qaCompany->id;
        $supplier->save();
        return $supplier;
    }
    private function order(): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'supplier_id' => $this->supplier()->id,
            'tracking_number' => 'QA-'.uniqid(),
            'shipping_cost' => 12.50,
        ]);
        $order->company_id = $this->qaCompany->id;
        $order->save();
        return $order;
    }
    protected function fixture(): Model
    {
        return $this->order();
    }
    protected function createPayload(): array
    {
        return ['supplier_id' => $this->supplier()->id,
            'tracking_number' => 'NEW-'.uniqid(), 'shipping_cost' => 14.50];
    }
    protected function updatePayload(): array
    {
        return ['tracking_number' => 'EDIT-'.uniqid(), 'shipping_cost' => 21.75];
    }
}
