<?php

namespace App\Services\Purchasing;

use App\Exceptions\PurchaseReceiptAlreadyExistsException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderProduct;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
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

    /**
     * Receive the remaining order (legacy API) or selected quantities per
     * purchase-order line. All stock movements and receipt rows are atomic.
     *
     * Partial requests must use a distinct receipt key so retries cannot
     * silently double-post the same delivery.
     *
     * @param array<int, array<string, int>>|null $requestedLines
     */
    public function receive(
        int $companyId,
        int $purchaseOrderId,
        int $warehouseId,
        ?int $userId = null,
        ?array $requestedLines = null,
        ?string $receiptKey = null
    ): PurchaseReceipt {
        if ($requestedLines !== null && !$receiptKey) {
            throw new DomainException(
                'Partial receiving requires a receipt key.'
            );
        }

        return DB::transaction(function () use (
            $companyId,
            $purchaseOrderId,
            $warehouseId,
            $userId,
            $requestedLines,
            $receiptKey
        ) {
            /*
             * All receipts of the same order synchronize on the order lock.
             * The line-level balance check therefore sees committed receipts
             * from previous requests, including concurrent attempts.
             */
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrderId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new DomainException(
                    'Purchase order does not belong to the selected company.'
                );
            }

            if ($receiptKey !== null && PurchaseReceipt::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $receiptKey)
                ->exists()) {
                throw new PurchaseReceiptAlreadyExistsException(
                    'Receipt key was already posted.'
                );
            }

            /*
             * Historical one-time receipts predate purchase_receipt_lines.
             * Do not infer their original line quantities or receive them
             * again: treat them as fully posted and immutable.
             */
            if (PurchaseReceipt::query()
                ->where('purchase_order_id', $order->id)
                ->whereDoesntHave('lines')
                ->exists()) {
                throw new PurchaseReceiptAlreadyExistsException(
                    'Purchase order has already been received.'
                );
            }

            $orderLines = PurchaseOrderProduct::query()
                ->where('purchase_order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orderLines->isEmpty()) {
                throw new DomainException(
                    'Purchase order has no lines to receive.'
                );
            }

            $previous = DB::table('purchase_receipt_lines as receipt_lines')
                ->join(
                    'purchase_receipts as receipts',
                    'receipts.id',
                    '=',
                    'receipt_lines.purchase_receipt_id'
                )
                ->where('receipts.purchase_order_id', $order->id)
                ->select('receipt_lines.purchase_order_product_id')
                ->selectRaw('SUM(receipt_lines.quantity) as received')
                ->groupBy('receipt_lines.purchase_order_product_id')
                ->pluck('received', 'purchase_order_product_id');

            $requestedById = null;

            if ($requestedLines !== null) {
                if (!$requestedLines) {
                    throw new DomainException(
                        'Receipt must contain at least one line.'
                    );
                }

                $requestedById = [];

                foreach ($requestedLines as $entry) {
                    $id = (int) $entry['purchase_order_product_id'];

                    if (array_key_exists($id, $requestedById)) {
                        throw new DomainException(
                            'Repeated purchase-order line in receipt.'
                        );
                    }

                    $requestedById[$id] = (int) $entry['quantity'];
                }

                $validIds = $orderLines->pluck('id')->map(function ($id) {
                    return (int) $id;
                })->all();

                foreach (array_keys($requestedById) as $id) {
                    if (!in_array($id, $validIds, true)) {
                        throw new DomainException(
                            'Receipt line does not belong to this purchase order.'
                        );
                    }
                }
            }

            $toReceive = [];

            foreach ($orderLines as $line) {
                $id = (int) $line->id;
                $ordered = (int) $line->quantity;
                $received = (int) ($previous[$id] ?? 0);
                $remaining = $ordered - $received;

                if ($ordered <= 0 || $remaining < 0) {
                    throw new DomainException(
                        'Purchase order has invalid or over-received quantities.'
                    );
                }

                if ($requestedById !== null) {
                    if (!array_key_exists($id, $requestedById)) {
                        continue;
                    }
                    $quantity = $requestedById[$id];
                } else {
                    $quantity = $remaining;
                }

                if ($quantity <= 0 || $quantity > $remaining) {
                    throw new DomainException(
                        'Receipt quantity exceeds the remaining order balance.'
                    );
                }

                $toReceive[] = [
                    'line' => $line,
                    'quantity' => $quantity,
                ];
            }

            if (!$toReceive) {
                throw new PurchaseReceiptAlreadyExistsException(
                    'Purchase order is fully received.'
                );
            }

            $receipt = new PurchaseReceipt([
                'purchase_order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'received_by_user_id' => $userId,
                'received_at' => now(),
                'idempotency_key' => $receiptKey,
            ]);
            $receipt->company_id = $companyId;
            $receipt->save();

            foreach ($toReceive as $item) {
                $line = $item['line'];
                $quantity = $item['quantity'];

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

                PurchaseReceiptLine::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_product_id' => $line->id,
                    'product_id' => $line->product_id,
                    'quantity' => $quantity,
                ]);
            }

            return $receipt->fresh()->load('lines');
        }, 3);
    }
}
