<?php

namespace App\Http\Controllers;

use App\Exceptions\PurchaseReceiptAlreadyExistsException;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseReceiptService;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\Request;

class PurchaseReceiptController extends Controller
{
    public function store(
        Request $request,
        $id,
        TenantContext $tenantContext,
        PurchaseReceiptService $receipts
    ) {
        $request->validate([
            'warehouse_id' => 'required|integer',
            'company_id' => 'sometimes|nullable',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        /*
         * Receipt writes require an exactly-owned order. Legacy NULL-company
         * orders stay readable through the Phase 5A compatibility contract,
         * but cannot be posted into a tenant's inventory.
         */
        $purchaseOrder = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'message' => 'Orden de compra no encontrada',
            ], 404);
        }

        /*
         * Resolve the warehouse in the same tenant before beginning receipt
         * posting so a foreign warehouse behaves as an unavailable resource.
         */
        $warehouse = Warehouse::query()
            ->where('company_id', $companyId)
            ->find($request->input('warehouse_id'));

        if (!$warehouse) {
            return response()->json([
                'message' => 'Almacén no encontrado',
            ], 404);
        }

        try {
            $receipt = $receipts->receive(
                $companyId,
                (int) $purchaseOrder->id,
                (int) $warehouse->id,
                (int) $request->user()->id
            );

            return response()->json($receipt, 201);
        } catch (PurchaseReceiptAlreadyExistsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
