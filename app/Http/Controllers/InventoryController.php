<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    private InventoryService $inventoryService;
    private TenantContext $tenantContext;

    public function __construct(
        InventoryService $inventoryService,
        TenantContext $tenantContext
    ) {
        $this->inventoryService = $inventoryService;
        $this->tenantContext = $tenantContext;
    }

    public function index(Request $request)
    {
        $companyId = $this->tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Inventory::query()
            ->with(['product', 'warehouse'])
            ->orderBy('warehouse_id')
            ->orderBy('product_id');

        if ($companyId !== null) {
            $query->whereHas('warehouse', function ($warehouseQuery) use ($companyId) {
                $warehouseQuery->where('company_id', $companyId);
            });
        }

        return response()->json($query->get());
    }

    public function create(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*.id' => 'required|integer|exists:products,id',
            'product_ids.*.quantity' => 'required|integer|min:0',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
        ]);

        try {
            $companyId = $this->resolveCompanyIdForWarehouse(
                $request,
                (int) $request->input('warehouse_id')
            );

            $warehouseId = (int) $request->input('warehouse_id');
            $userId = (int) $request->user()->id;

            $inventories = DB::transaction(function () use (
                $request,
                $companyId,
                $warehouseId,
                $userId
            ) {
                $created = [];

                foreach ($request->input('product_ids') as $product) {
                    $productId = (int) $product['id'];
                    $quantity = (int) $product['quantity'];

                    $this->inventoryService->adjust(
                        $companyId,
                        $warehouseId,
                        $productId,
                        $quantity,
                        $userId,
                        'inventory_create',
                        null,
                        'Initial inventory assignment'
                    );

                    $created[] = Inventory::query()
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->firstOrFail();
                }

                return $created;
            });

            return response()->json([
                'inventories' => $inventories,
            ], 201);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    public function get(Request $request, $id)
    {
        $inventory = Inventory::with(['product', 'warehouse'])
            ->findOrFail($id);

        $this->authorizeInventory($request, $inventory);

        return response()->json($inventory, 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
            'product_id' => 'sometimes|integer',
            'warehouse_id' => 'sometimes|integer',
        ]);

        $inventory = Inventory::with('warehouse')->findOrFail($id);
        $companyId = $this->authorizeInventory($request, $inventory);

        if (
            $request->has('product_id') &&
            (int) $request->input('product_id') !== (int) $inventory->product_id
        ) {
            throw ValidationException::withMessages([
                'product_id' => [
                    'The product of an existing inventory balance cannot be changed.',
                ],
            ]);
        }

        if (
            $request->has('warehouse_id') &&
            (int) $request->input('warehouse_id') !== (int) $inventory->warehouse_id
        ) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The warehouse of an existing inventory balance cannot be changed.',
                ],
            ]);
        }

        try {
            $this->inventoryService->adjust(
                $companyId,
                (int) $inventory->warehouse_id,
                (int) $inventory->product_id,
                (int) $request->input('quantity'),
                (int) $request->user()->id,
                'inventory_update',
                (int) $inventory->id,
                $request->input('notes')
            );

            return response()->json(
                $inventory->fresh(['product', 'warehouse']),
                200
            );
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    public function delete(Request $request, $id)
    {
        $inventory = Inventory::with('warehouse')->findOrFail($id);
        $companyId = $this->authorizeInventory($request, $inventory);

        try {
            if ((int) $inventory->quantity > 0) {
                $this->inventoryService->adjust(
                    $companyId,
                    (int) $inventory->warehouse_id,
                    (int) $inventory->product_id,
                    0,
                    (int) $request->user()->id,
                    'inventory_delete',
                    (int) $inventory->id,
                    'Inventory balance deactivated'
                );
            }

            return response()->json(null, 204);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    public function saveInventoryProducts(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'new_product_ids' => 'nullable|array',
            'new_product_ids.*.id' => 'required|integer|exists:products,id',
            'new_product_ids.*.quantity' => 'required|integer|min:1',
            'product_ids' => 'nullable|array',
            'product_ids.*.id' => 'required|integer|exists:products,id',
            'product_ids.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $warehouseId = (int) $request->input('warehouse_id');
            $companyId = $this->resolveCompanyIdForWarehouse(
                $request,
                $warehouseId
            );
            $userId = (int) $request->user()->id;

            DB::transaction(function () use (
                $request,
                $companyId,
                $warehouseId,
                $userId
            ) {
                $groups = [
                    $request->input('new_product_ids', []),
                    $request->input('product_ids', []),
                ];

                foreach ($groups as $products) {
                    foreach ($products as $product) {
                        $this->inventoryService->receive(
                            $companyId,
                            $warehouseId,
                            (int) $product['id'],
                            (int) $product['quantity'],
                            $userId,
                            'inventory_assignment',
                            null,
                            'Inventory assigned to warehouse'
                        );
                    }
                }
            });

            return response()->json([
                'message' => 'Inventario actualizado',
            ], 201);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    public function removeAssignedInventory(Request $request)
    {
        $request->validate([
            'company_id' => 'sometimes|nullable|integer|exists:companies,id',
            'product_ids' => 'required|array|min:1',
            'product_ids.*.id' => 'required|integer|exists:products,id',
            'product_ids.*.warehouse_id' => 'sometimes|nullable|integer|exists:warehouses,id',
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->input('product_ids') as $product) {
                    $productId = (int) $product['id'];
                    $warehouseId = isset($product['warehouse_id'])
                        ? (int) $product['warehouse_id']
                        : null;

                    if ($warehouseId !== null) {
                        $companyId = $this->resolveCompanyIdForWarehouse(
                            $request,
                            $warehouseId
                        );

                        $inventory = Inventory::query()
                            ->where('product_id', $productId)
                            ->where('warehouse_id', $warehouseId)
                            ->first();

                        if (!$inventory || (int) $inventory->quantity === 0) {
                            continue;
                        }
                    } else {
                        $companyId = $this->tenantContext->resolveCompanyId(
                            $request->user(),
                            $request->input('company_id'),
                            true
                        );

                        $candidates = Inventory::query()
                            ->where('product_id', $productId)
                            ->where('quantity', '>', 0)
                            ->whereHas('warehouse', function ($query) use ($companyId) {
                                $query->where('company_id', $companyId);
                            })
                            ->get();

                        if ($candidates->isEmpty()) {
                            continue;
                        }

                        if ($candidates->count() > 1) {
                            throw ValidationException::withMessages([
                                'product_ids' => [
                                    'warehouse_id is required when a product has stock in more than one warehouse.',
                                ],
                            ]);
                        }

                        $inventory = $candidates->first();
                        $warehouseId = (int) $inventory->warehouse_id;
                    }

                    $this->inventoryService->adjust(
                        (int) $companyId,
                        (int) $warehouseId,
                        $productId,
                        0,
                        (int) $request->user()->id,
                        'inventory_unassign',
                        (int) $inventory->id,
                        'Inventory unassigned from warehouse'
                    );
                }
            });

            return response()->json(null, 204);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
    }

    private function resolveCompanyIdForWarehouse(
        Request $request,
        int $warehouseId
    ): int {
        $warehouse = Warehouse::findOrFail($warehouseId);

        if ($warehouse->company_id === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The selected warehouse is not assigned to a company.',
                ],
            ]);
        }

        return (int) $this->tenantContext->resolveCompanyId(
            $request->user(),
            (int) $warehouse->company_id,
            true
        );
    }

    private function authorizeInventory(
        Request $request,
        Inventory $inventory
    ): int {
        $inventory->loadMissing('warehouse');

        if (
            !$inventory->warehouse ||
            $inventory->warehouse->company_id === null
        ) {
            throw ValidationException::withMessages([
                'inventory' => [
                    'The inventory balance is not attached to a valid company warehouse.',
                ],
            ]);
        }

        return (int) $this->tenantContext->resolveCompanyId(
            $request->user(),
            (int) $inventory->warehouse->company_id,
            true
        );
    }

    private function domainError(DomainException $exception)
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], 422);
    }
}
