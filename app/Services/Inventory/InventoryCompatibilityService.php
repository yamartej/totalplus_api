<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;

class InventoryCompatibilityService
{
    private const SALE_REFERENCE_TYPES = [
        'sale',
        'sale_void',
        'sale_detail_update',
        'sale_detail_remove',
    ];

    public function totalOnHand(
        Product $product,
        ?int $companyId = null
    ): int {
        if ($product->relationLoaded('inventories')) {
            $inventories = $product->inventories;

            if ($companyId !== null) {
                $inventories = $inventories->filter(
                    function ($inventory) use ($companyId) {
                        $inventory->loadMissing('warehouse');

                        return $inventory->warehouse !== null
                            && (int) $inventory->warehouse->company_id
                                === $companyId;
                    }
                );
            }

            return (int) $inventories->sum('quantity');
        }

        $query = $product->inventories();

        if ($companyId !== null) {
            $query->whereHas(
                'warehouse',
                function ($warehouseQuery) use ($companyId) {
                    $warehouseQuery->where(
                        'company_id',
                        $companyId
                    );
                }
            );
        }

        return (int) $query->sum('quantity');
    }

    public function salesDepletionQuantity(
        Product $product,
        ?int $companyId = null
    ): int {
        $query = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->whereIn(
                'reference_type',
                self::SALE_REFERENCE_TYPES
            );

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $netSaleInventoryDelta = (int)
            $query->sum('quantity_delta');

        return max(0, -$netSaleInventoryDelta);
    }

    public function unallocatedQuantity(
        Product $product,
        ?int $companyId = null
    ): int {
        $legacyQuantity = max(
            0,
            (int) $product->quantity
        );

        $inventoryTotal = $this->totalOnHand(
            $product,
            $companyId
        );

        $salesDepletion = $this->salesDepletionQuantity(
            $product,
            $companyId
        );

        return max(
            0,
            $legacyQuantity
                - $inventoryTotal
                - $salesDepletion
        );
    }

    public function appendCompatibilityAttributes(
        Product $product,
        ?int $companyId = null
    ): Product {
        $legacyQuantity = (int) $product->quantity;

        $inventoryTotal = $this->totalOnHand(
            $product,
            $companyId
        );

        $salesDepletion = $this->salesDepletionQuantity(
            $product,
            $companyId
        );

        $product->setAttribute(
            'legacy_quantity',
            $legacyQuantity
        );

        $product->setAttribute(
            'inventory_total_quantity',
            $inventoryTotal
        );

        $product->setAttribute(
            'unallocated_quantity',
            max(
                0,
                $legacyQuantity
                    - $inventoryTotal
                    - $salesDepletion
            )
        );

        return $product;
    }
}
