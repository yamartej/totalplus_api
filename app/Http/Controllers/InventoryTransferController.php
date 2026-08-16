<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryTransferController extends Controller
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

    public function store(Request $request)
    {
        $request->validate([
            'source_warehouse_id' => 'required|integer|exists:warehouses,id',
            'destination_warehouse_id' => 'required|integer|different:source_warehouse_id|exists:warehouses,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reference_type' => 'sometimes|nullable|string|max:100',
            'reference_id' => 'sometimes|nullable|integer|min:1',
            'notes' => 'sometimes|nullable|string',
        ]);

        $sourceWarehouse = Warehouse::findOrFail(
            (int) $request->input('source_warehouse_id')
        );

        if ($sourceWarehouse->company_id === null) {
            throw ValidationException::withMessages([
                'source_warehouse_id' => [
                    'The source warehouse is not assigned to a company.',
                ],
            ]);
        }

        $companyId = (int) $this->tenantContext->resolveCompanyId(
            $request->user(),
            (int) $sourceWarehouse->company_id,
            true
        );

        try {
            $movements = $this->inventoryService->transfer(
                $companyId,
                (int) $request->input('source_warehouse_id'),
                (int) $request->input('destination_warehouse_id'),
                (int) $request->input('product_id'),
                (int) $request->input('quantity'),
                (int) $request->user()->id,
                $request->input('reference_type'),
                $request->input('reference_id'),
                $request->input('notes')
            );

            return response()->json([
                'message' => 'Inventory transferred successfully.',
                'movements' => [
                    'out' => $movements['out'],
                    'in' => $movements['in'],
                ],
            ], 201);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
