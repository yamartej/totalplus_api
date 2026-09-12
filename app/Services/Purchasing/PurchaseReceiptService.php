<?php

namespace App\Services\Purchasing;

use App\Exceptions\PurchaseReceiptAlreadyExistsException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderProduct;
use App\Models\PurchaseReceipt;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptService
{
    private InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    public function receive(
        int $companyId,
        int $purchaseOrderId,
        int $warehouseId,
        ?int $userId = null
    ): PurchaseReceipt {
        return DB::transaction(function () use (
            $companyId,
            $purchaseOrderId,
            $warehouseId,
            $userId
        ) {
            /*
             * Lock the order so two concurrent receipt attempts serialize
             * before checking idempotency.
             */
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrderId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$purchaseOrder) {
                throw new DomainException(
                    'Purchase order does not belong to the selected company.'
                );
            }

            if (
                PurchaseReceipt::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->exists()
            ) {
                throw new PurchaseReceiptAlreadyExistsException(
                    'Purchase order has already been received.'
                );
            }

            $lines = PurchaseOrderProduct::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw new DomainException(
                    'Purchase order has no lines to receive.'
                );
            }

            $receipt = new PurchaseReceipt([
                'purchase_order_id' => $purchaseOrder->id,
                'warehouse_id' => $warehouseId,
                'received_by_user_id' => $userId,
                'received_at' => now(),
            ]);

            /*
             * Tenant ownership is assigned explicitly rather than accepting
             * company_id through mass assignment.
             */
            $receipt->company_id = $companyId;
            $receipt->save();

            foreach ($lines as $line) {
                $quantity = (int) $line->quantity;

                if ($quantity <= 0) {
                    throw new DomainException(
                        'Purchase order line quantity must be greater than zero.'
                    );
                }

                /*
                 * InventoryService is the canonical stock engine. It validates
                 * warehouse/product tenancy, changes inventories.quantity and
                 * writes the immutable receive movement. The surrounding
                 * transaction guarantees all-or-nothing receipt posting.
                 */
                $this->inventory->receive(
                    $companyId,
                    $warehouseId,
                    (int) $line->product_id,
                    $quantity,
                    $userId,
                    'purchase_receipt',
                    (int) $receipt->id,
                    'Transactional purchase order receipt.'
                );
            }

            /*
             * products.quantity intentionally remains untouched. Current stock
             * is represented only by inventories.quantity.
             */
            return $receipt->fresh();
        }, 3);
    }
}
