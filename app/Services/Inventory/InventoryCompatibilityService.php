<?php

namespace App\Services\Inventory;

use App\Models\Product;

class InventoryCompatibilityService
{
    /**
     * Canonical current stock is the sum of inventories.quantity.
     *
     * products.quantity remains a legacy purchase/received quantity during
     * Phase 2E and product_warehouse.quantity is not considered stock.
     *
     * When a company is resolved, only inventory balances attached to that
     * company's warehouses contribute to the projection.
     */
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

    /**
     * Quantity that has not yet been assigned to warehouse inventory.
     *
     * This is a temporary compatibility concept while products.quantity is
     * still used by the purchase/batch/cost workflow.
     */
    public function unallocatedQuantity(
        Product $product,
        ?int $companyId = null
    ): int {
        $legacyQuantity = max(0, (int) $product->quantity);
        $inventoryTotal = $this->totalOnHand(
            $product,
            $companyId
        );

        return max(0, $legacyQuantity - $inventoryTotal);
    }

    /**
     * Add explicit compatibility attributes without changing persisted data.
     */
    public function appendCompatibilityAttributes(
        Product $product,
        ?int $companyId = null
    ): Product {
        $legacyQuantity = (int) $product->quantity;
        $inventoryTotal = $this->totalOnHand(
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
            max(0, $legacyQuantity - $inventoryTotal)
        );

        return $product;
    }
}
