<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Support\Facades\DB;

class SaleTransactionService
{
    private InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    public function create(
        int $companyId,
        Customer $customer,
        User $seller,
        PointOfSale $pop,
        string $typeOfSale,
        array $carts,
        ?int $actorUserId = null
    ): Sale {
        return DB::transaction(function () use (
            $companyId,
            $customer,
            $seller,
            $pop,
            $typeOfSale,
            $carts,
            $actorUserId
        ) {
            $lines = $this->prepareLines(
                $companyId,
                $carts
            );

            $totalCents = 0;

            foreach ($lines as $line) {
                $totalCents += $line['line_total_cents'];
            }

            $sale = Sale::create([
                'customer_id' => $customer->id,
                'seller_id' => $seller->id,
                'pop_id' => $pop->id,
                'total_amount' => $this->fromCents($totalCents),
                'type_of_sale' => $typeOfSale,
                'company_id' => $companyId,
            ]);

            foreach ($lines as $line) {
                $this->inventory->issue(
                    $companyId,
                    $line['warehouse_id'],
                    $line['product_id'],
                    $line['quantity'],
                    $actorUserId,
                    'sale',
                    $sale->id,
                    'Transactional sale issue.'
                );

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product_id'],
                    'warehouse_id' => $line['warehouse_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                ]);
            }

            return $sale->load([
                'details.product',
                'details.warehouse',
            ]);
        }, 3);
    }

    public function void(
        Sale $sale,
        int $companyId,
        ?int $actorUserId = null
    ): void {
        DB::transaction(function () use (
            $sale,
            $companyId,
            $actorUserId
        ) {
            $lockedSale = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedSale) {
                throw new DomainException(
                    'Sale no longer exists.'
                );
            }

            $this->assertSaleCompany(
                $lockedSale,
                $companyId
            );

            $details = SaleDetail::query()
                ->where('sale_id', $lockedSale->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($details as $detail) {
                $productId = $this->detailProductId($detail);
                $quantity = $this->detailQuantity($detail);

                $warehouseId = $this->resolveVoidWarehouseId(
                    $companyId,
                    $productId,
                    $detail->warehouse_id !== null
                        ? (int) $detail->warehouse_id
                        : null
                );

                $this->inventory->receive(
                    $companyId,
                    $warehouseId,
                    $productId,
                    $quantity,
                    $actorUserId,
                    'sale_void',
                    $lockedSale->id,
                    'Transactional sale void.'
                );
            }

            foreach ($details as $detail) {
                $detail->delete();
            }

            $lockedSale->delete();
        }, 3);
    }

    public function updateDetail(
        SaleDetail $detail,
        int $companyId,
        int $newQuantity,
        ?int $actorUserId = null
    ): array {
        if ($newQuantity <= 0) {
            throw new DomainException(
                'Sale detail quantity must be greater than zero.'
            );
        }

        return DB::transaction(function () use (
            $detail,
            $companyId,
            $newQuantity,
            $actorUserId
        ) {
            $lockedDetail = SaleDetail::query()
                ->whereKey($detail->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedDetail) {
                throw new DomainException(
                    'Sale detail no longer exists.'
                );
            }

            $lockedSale = Sale::query()
                ->whereKey($lockedDetail->sale_id)
                ->lockForUpdate()
                ->first();

            if (!$lockedSale) {
                throw new DomainException(
                    'Sale for this detail no longer exists.'
                );
            }

            $this->assertSaleCompany(
                $lockedSale,
                $companyId
            );

            $productId = $this->detailProductId(
                $lockedDetail
            );
            $oldQuantity = $this->detailQuantity(
                $lockedDetail
            );

            if (
                $lockedDetail->unit_price === null
                || $lockedDetail->unit_price === ''
            ) {
                throw new DomainException(
                    'Legacy sale detail has no unit_price and cannot be edited safely.'
                );
            }

            $warehouseId = $this->resolveVoidWarehouseId(
                $companyId,
                $productId,
                $lockedDetail->warehouse_id !== null
                    ? (int) $lockedDetail->warehouse_id
                    : null
            );

            $quantityDelta = $newQuantity - $oldQuantity;

            if ($quantityDelta > 0) {
                $this->inventory->issue(
                    $companyId,
                    $warehouseId,
                    $productId,
                    $quantityDelta,
                    $actorUserId,
                    'sale_detail_update',
                    $lockedDetail->id,
                    'Sale detail quantity increased.'
                );
            } elseif ($quantityDelta < 0) {
                $this->inventory->receive(
                    $companyId,
                    $warehouseId,
                    $productId,
                    abs($quantityDelta),
                    $actorUserId,
                    'sale_detail_update',
                    $lockedDetail->id,
                    'Sale detail quantity decreased.'
                );
            }

            $lockedDetail->quantity = $newQuantity;

            if ($lockedDetail->warehouse_id === null) {
                $lockedDetail->warehouse_id = $warehouseId;
            }

            $lockedDetail->save();

            $this->recalculateSaleTotal(
                $lockedSale
            );

            return [
                'detail' => $lockedDetail->fresh([
                    'product',
                    'warehouse',
                ]),
                'sale' => $lockedSale->fresh(),
            ];
        }, 3);
    }

    public function removeDetail(
        SaleDetail $detail,
        int $companyId,
        ?int $actorUserId = null
    ): array {
        return DB::transaction(function () use (
            $detail,
            $companyId,
            $actorUserId
        ) {
            $lockedDetail = SaleDetail::query()
                ->whereKey($detail->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedDetail) {
                throw new DomainException(
                    'Sale detail no longer exists.'
                );
            }

            $lockedSale = Sale::query()
                ->whereKey($lockedDetail->sale_id)
                ->lockForUpdate()
                ->first();

            if (!$lockedSale) {
                throw new DomainException(
                    'Sale for this detail no longer exists.'
                );
            }

            $this->assertSaleCompany(
                $lockedSale,
                $companyId
            );

            $productId = $this->detailProductId(
                $lockedDetail
            );
            $quantity = $this->detailQuantity(
                $lockedDetail
            );

            $warehouseId = $this->resolveVoidWarehouseId(
                $companyId,
                $productId,
                $lockedDetail->warehouse_id !== null
                    ? (int) $lockedDetail->warehouse_id
                    : null
            );

            $this->inventory->receive(
                $companyId,
                $warehouseId,
                $productId,
                $quantity,
                $actorUserId,
                'sale_detail_remove',
                $lockedDetail->id,
                'Sale detail removed.'
            );

            $detailId = $lockedDetail->id;
            $saleId = $lockedSale->id;

            $lockedDetail->delete();

            $remainingDetails = SaleDetail::query()
                ->where('sale_id', $saleId)
                ->count();

            if ($remainingDetails === 0) {
                $lockedSale->delete();

                return [
                    'detail_id' => $detailId,
                    'sale_id' => $saleId,
                    'sale_deleted' => true,
                    'sale' => null,
                ];
            }

            $this->recalculateSaleTotal(
                $lockedSale
            );

            return [
                'detail_id' => $detailId,
                'sale_id' => $saleId,
                'sale_deleted' => false,
                'sale' => $lockedSale->fresh(),
            ];
        }, 3);
    }

    private function prepareLines(
        int $companyId,
        array $carts
    ): array {
        $productIds = collect($carts)
            ->pluck('productId')
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($carts as $cart) {
            $productId = (int) $cart['productId'];
            $quantity = (int) $cart['quantity'];

            /** @var Product|null $product */
            $product = $products->get($productId);

            if (!$product) {
                throw new DomainException(
                    'Product does not exist.'
                );
            }

            if (
                $product->company_id !== null
                && (int) $product->company_id !== $companyId
            ) {
                throw new DomainException(
                    'Product does not belong to the selected company.'
                );
            }

            $requestedWarehouseId = null;

            if (
                array_key_exists('warehouse_id', $cart)
                && $cart['warehouse_id'] !== null
                && $cart['warehouse_id'] !== ''
            ) {
                $requestedWarehouseId =
                    (int) $cart['warehouse_id'];
            }

            $warehouseId = $this->resolveWarehouseId(
                $companyId,
                $productId,
                $requestedWarehouseId
            );

            $key = $productId . ':' . $warehouseId;

            if (!isset($lines[$key])) {
                $lines[$key] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                ];
            }

            $lines[$key]['quantity'] += $quantity;
        }

        $productTotals = [];

        foreach ($lines as $line) {
            $productId = $line['product_id'];

            if (!isset($productTotals[$productId])) {
                $productTotals[$productId] = 0;
            }

            $productTotals[$productId] += $line['quantity'];
        }

        foreach ($lines as $key => $line) {
            /** @var Product $product */
            $product = $products->get($line['product_id']);

            $unitPriceCents = $this->resolveUnitPriceCents(
                $product,
                $productTotals[$line['product_id']]
            );

            $lines[$key]['unit_price_cents'] =
                $unitPriceCents;
            $lines[$key]['unit_price'] =
                $this->fromCents($unitPriceCents);
            $lines[$key]['line_total_cents'] =
                $unitPriceCents * $line['quantity'];
        }

        $lines = array_values($lines);

        usort(
            $lines,
            function (array $left, array $right): int {
                $warehouseComparison =
                    $left['warehouse_id']
                    <=> $right['warehouse_id'];

                if ($warehouseComparison !== 0) {
                    return $warehouseComparison;
                }

                return $left['product_id']
                    <=> $right['product_id'];
            }
        );

        return $lines;
    }

    private function resolveWarehouseId(
        int $companyId,
        int $productId,
        ?int $requestedWarehouseId
    ): int {
        if ($requestedWarehouseId !== null) {
            $inventory = Inventory::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $requestedWarehouseId)
                ->whereHas(
                    'warehouse',
                    function ($warehouse) use ($companyId) {
                        $warehouse->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->first();

            if (!$inventory) {
                throw new DomainException(
                    'Inventory is not available for the selected warehouse and company.'
                );
            }

            return $requestedWarehouseId;
        }

        $warehouseIds = Inventory::query()
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->whereHas(
                'warehouse',
                function ($warehouse) use ($companyId) {
                    $warehouse->where(
                        'company_id',
                        $companyId
                    );
                }
            )
            ->orderBy('warehouse_id')
            ->pluck('warehouse_id')
            ->map(function ($warehouseId) {
                return (int) $warehouseId;
            })
            ->unique()
            ->values();

        if ($warehouseIds->count() === 0) {
            throw new DomainException(
                'No positive inventory is available for this product.'
            );
        }

        if ($warehouseIds->count() > 1) {
            throw new DomainException(
                'warehouse_id is required when a product has stock in multiple warehouses.'
            );
        }

        return (int) $warehouseIds->first();
    }

    private function resolveVoidWarehouseId(
        int $companyId,
        int $productId,
        ?int $recordedWarehouseId
    ): int {
        if ($recordedWarehouseId !== null) {
            $belongsToCompany = Inventory::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $recordedWarehouseId)
                ->whereHas(
                    'warehouse',
                    function ($warehouse) use ($companyId) {
                        $warehouse->where(
                            'company_id',
                            $companyId
                        );
                    }
                )
                ->exists();

            if (!$belongsToCompany) {
                throw new DomainException(
                    'Recorded sale warehouse is not valid for the selected company.'
                );
            }

            return $recordedWarehouseId;
        }

        $warehouseIds = Inventory::query()
            ->where('product_id', $productId)
            ->whereHas(
                'warehouse',
                function ($warehouse) use ($companyId) {
                    $warehouse->where(
                        'company_id',
                        $companyId
                    );
                }
            )
            ->orderBy('warehouse_id')
            ->pluck('warehouse_id')
            ->map(function ($warehouseId) {
                return (int) $warehouseId;
            })
            ->unique()
            ->values();

        if ($warehouseIds->count() !== 1) {
            throw new DomainException(
                'Legacy sale detail has no warehouse_id and cannot be reversed safely.'
            );
        }

        return (int) $warehouseIds->first();
    }

    private function resolveUnitPriceCents(
        Product $product,
        int $totalProductQuantity
    ): int {
        $price = $totalProductQuantity >= 3
            ? $product->wholesale_final_cost
            : $product->final_cost;

        if (
            $price === null
            || $price === ''
            || !is_numeric($price)
        ) {
            throw new DomainException(
                'Sale price is not configured for product '
                . $product->id
                . '.'
            );
        }

        $cents = $this->moneyToCents($price);

        if ($cents < 0) {
            throw new DomainException(
                'Sale price cannot be negative.'
            );
        }

        return $cents;
    }

    private function recalculateSaleTotal(
        Sale $sale
    ): void {
        $details = SaleDetail::query()
            ->where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        $totalCents = 0;

        foreach ($details as $detail) {
            $quantity = $this->detailQuantity(
                $detail
            );

            if (
                $detail->unit_price === null
                || $detail->unit_price === ''
            ) {
                throw new DomainException(
                    'Sale contains legacy details without unit_price and cannot be recalculated safely.'
                );
            }

            $totalCents +=
                $this->moneyToCents(
                    $detail->unit_price
                ) * $quantity;
        }

        $sale->total_amount =
            $this->fromCents($totalCents);
        $sale->save();
    }

    private function assertSaleCompany(
        Sale $sale,
        int $companyId
    ): void {
        $saleCompanyId = $sale->company_id;

        if (
            $saleCompanyId === null
            && $sale->customer_id !== null
        ) {
            $saleCompanyId = Customer::query()
                ->whereKey($sale->customer_id)
                ->value('company_id');
        }

        if (
            $saleCompanyId !== null
            && (int) $saleCompanyId !== $companyId
        ) {
            throw new DomainException(
                'Sale does not belong to the selected company.'
            );
        }
    }

    private function detailProductId(
        SaleDetail $detail
    ): int {
        if ($detail->product_id === null) {
            throw new DomainException(
                'Legacy sale detail has no product and cannot be processed safely.'
            );
        }

        return (int) $detail->product_id;
    }

    private function detailQuantity(
        SaleDetail $detail
    ): int {
        if (
            $detail->quantity === null
            || (int) $detail->quantity <= 0
        ) {
            throw new DomainException(
                'Sale detail quantity is invalid.'
            );
        }

        return (int) $detail->quantity;
    }

    private function moneyToCents($value): int
    {
        if (!is_numeric($value)) {
            throw new DomainException(
                'Invalid monetary value.'
            );
        }

        return (int) round(
            ((float) $value) * 100
        );
    }

    private function fromCents(int $cents): string
    {
        return number_format(
            $cents / 100,
            2,
            '.',
            ''
        );
    }
}
