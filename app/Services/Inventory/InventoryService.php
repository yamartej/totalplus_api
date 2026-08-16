<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function receive(
        int $companyId,
        int $warehouseId,
        int $productId,
        int $quantity,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new DomainException(
                'Receive quantity must be greater than zero.'
            );
        }

        return $this->changeBy(
            $companyId,
            $warehouseId,
            $productId,
            $quantity,
            InventoryMovement::TYPE_RECEIVE,
            $userId,
            $referenceType,
            $referenceId,
            $notes
        );
    }

    public function issue(
        int $companyId,
        int $warehouseId,
        int $productId,
        int $quantity,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new DomainException(
                'Issue quantity must be greater than zero.'
            );
        }

        return $this->changeBy(
            $companyId,
            $warehouseId,
            $productId,
            -$quantity,
            InventoryMovement::TYPE_ISSUE,
            $userId,
            $referenceType,
            $referenceId,
            $notes
        );
    }

    public function adjust(
        int $companyId,
        int $warehouseId,
        int $productId,
        int $targetQuantity,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): InventoryMovement {
        if ($targetQuantity < 0) {
            throw new DomainException(
                'Target inventory quantity cannot be negative.'
            );
        }

        return DB::transaction(function () use (
            $companyId,
            $warehouseId,
            $productId,
            $targetQuantity,
            $userId,
            $referenceType,
            $referenceId,
            $notes
        ) {
            $this->assertInventoryScope(
                $companyId,
                $warehouseId,
                $productId
            );

            $inventory = $this->lockOrCreateInventory(
                $productId,
                $warehouseId
            );

            $balanceBefore = (int) $inventory->quantity;
            $balanceAfter = $targetQuantity;
            $quantityDelta = $balanceAfter - $balanceBefore;

            $inventory->quantity = $balanceAfter;
            $inventory->save();

            return $this->recordMovement(
                $companyId,
                $warehouseId,
                $productId,
                InventoryMovement::TYPE_ADJUSTMENT,
                $quantityDelta,
                $balanceBefore,
                $balanceAfter,
                $userId,
                $referenceType,
                $referenceId,
                $notes
            );
        }, 3);
    }

    public function transfer(
        int $companyId,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        int $productId,
        int $quantity,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): array {
        if ($quantity <= 0) {
            throw new DomainException(
                'Transfer quantity must be greater than zero.'
            );
        }

        if ($sourceWarehouseId === $destinationWarehouseId) {
            throw new DomainException(
                'Source and destination warehouses must be different.'
            );
        }

        return DB::transaction(function () use (
            $companyId,
            $sourceWarehouseId,
            $destinationWarehouseId,
            $productId,
            $quantity,
            $userId,
            $referenceType,
            $referenceId,
            $notes
        ) {
            $this->assertInventoryScope(
                $companyId,
                $sourceWarehouseId,
                $productId
            );

            $this->assertInventoryScope(
                $companyId,
                $destinationWarehouseId,
                $productId
            );

            $warehouseIds = [
                $sourceWarehouseId,
                $destinationWarehouseId,
            ];

            sort($warehouseIds);

            $locked = [];

            foreach ($warehouseIds as $warehouseId) {
                $locked[$warehouseId] = $this->lockOrCreateInventory(
                    $productId,
                    $warehouseId
                );
            }

            $source = $locked[$sourceWarehouseId];
            $destination = $locked[$destinationWarehouseId];

            $sourceBefore = (int) $source->quantity;
            $destinationBefore = (int) $destination->quantity;

            if ($sourceBefore < $quantity) {
                throw new DomainException(
                    'Insufficient stock for this transfer.'
                );
            }

            $sourceAfter = $sourceBefore - $quantity;
            $destinationAfter = $destinationBefore + $quantity;

            $source->quantity = $sourceAfter;
            $source->save();

            $destination->quantity = $destinationAfter;
            $destination->save();

            $transferOut = $this->recordMovement(
                $companyId,
                $sourceWarehouseId,
                $productId,
                InventoryMovement::TYPE_TRANSFER_OUT,
                -$quantity,
                $sourceBefore,
                $sourceAfter,
                $userId,
                $referenceType,
                $referenceId,
                $notes
            );

            $transferIn = $this->recordMovement(
                $companyId,
                $destinationWarehouseId,
                $productId,
                InventoryMovement::TYPE_TRANSFER_IN,
                $quantity,
                $destinationBefore,
                $destinationAfter,
                $userId,
                $referenceType,
                $referenceId,
                $notes
            );

            return [
                'out' => $transferOut,
                'in' => $transferIn,
            ];
        }, 3);
    }

    private function changeBy(
        int $companyId,
        int $warehouseId,
        int $productId,
        int $quantityDelta,
        string $type,
        ?int $userId,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notes
    ): InventoryMovement {
        return DB::transaction(function () use (
            $companyId,
            $warehouseId,
            $productId,
            $quantityDelta,
            $type,
            $userId,
            $referenceType,
            $referenceId,
            $notes
        ) {
            $this->assertInventoryScope(
                $companyId,
                $warehouseId,
                $productId
            );

            $inventory = $this->lockOrCreateInventory(
                $productId,
                $warehouseId
            );

            $balanceBefore = (int) $inventory->quantity;
            $balanceAfter = $balanceBefore + $quantityDelta;

            if ($balanceAfter < 0) {
                throw new DomainException(
                    'Insufficient stock for this operation.'
                );
            }

            $inventory->quantity = $balanceAfter;
            $inventory->save();

            return $this->recordMovement(
                $companyId,
                $warehouseId,
                $productId,
                $type,
                $quantityDelta,
                $balanceBefore,
                $balanceAfter,
                $userId,
                $referenceType,
                $referenceId,
                $notes
            );
        }, 3);
    }

    private function assertInventoryScope(
        int $companyId,
        int $warehouseId,
        int $productId
    ): void {
        $warehouse = Warehouse::query()
            ->whereKey($warehouseId)
            ->where('company_id', $companyId)
            ->first();

        if (!$warehouse) {
            throw new DomainException(
                'Warehouse does not belong to the selected company.'
            );
        }

        $product = Product::query()->find($productId);

        if (!$product) {
            throw new DomainException(
                'Product does not exist.'
            );
        }

        if (
            $product->company_id !== null &&
            (int) $product->company_id !== $companyId
        ) {
            throw new DomainException(
                'Product does not belong to the selected company.'
            );
        }
    }

    private function lockOrCreateInventory(
        int $productId,
        int $warehouseId
    ): Inventory {
        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($inventory) {
            return $inventory;
        }

        try {
            Inventory::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
        }

        return Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recordMovement(
        int $companyId,
        int $warehouseId,
        int $productId,
        string $type,
        int $quantityDelta,
        int $balanceBefore,
        int $balanceAfter,
        ?int $userId,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notes
    ): InventoryMovement {
        return InventoryMovement::create([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => $userId,
            'notes' => $notes,
        ]);
    }
}
