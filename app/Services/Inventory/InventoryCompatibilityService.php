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
     */
    public function totalOnHand(Product $product): int
    {
        if (!$product->relationLoaded('inventories')) {
            $product->load('inventories');
        }

        return (int) $product->inventories->sum('quantity');
    }

    /**
     * Quantity that has not yet been assigned to warehouse inventory.
     *
     * This is a temporary compatibility concept while products.quantity is
     * still used by the purchase/batch/cost workflow.
     */
    public function unallocatedQuantity(Product $product): int
    {
        $legacyQuantity = max(0, (int) $product->quantity);
        $inventoryTotal = $this->totalOnHand($product);

        return max(0, $legacyQuantity - $inventoryTotal);
    }

    /**
     * Add explicit compatibility attributes without changing persisted data.
     */
    public function appendCompatibilityAttributes(Product $product): Product
    {
        $legacyQuantity = (int) $product->quantity;
        $inventoryTotal = $this->totalOnHand($product);

        $product->setAttribute('legacy_quantity', $legacyQuantity);
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
