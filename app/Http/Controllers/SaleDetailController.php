<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\Sales\SaleTransactionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class SaleDetailController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show($id)
    {
        //
    }

    public function update(
        Request $request,
        $id,
        TenantContext $tenant,
        SaleTransactionService $sales
    ) {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $detail = SaleDetail::with([
            'sale.customer',
        ])->find($id);

        if (!$detail) {
            return response()->json([
                'message' => 'Detalle de venta no encontrado',
            ], 404);
        }

        if (!$detail->sale) {
            return response()->json([
                'message' =>
                    'La venta asociada al detalle no existe',
            ], 422);
        }

        $companyId = $this->resolveCompanyId(
            $request,
            $tenant,
            $detail->sale
        );

        try {
            $result = $sales->updateDetail(
                $detail,
                $companyId,
                (int) $request->input('quantity'),
                (int) $request->user()->id
            );

            // Preserve the legacy response shape:
            // [saleDetail, sale].
            return response()->json([
                $result['detail'],
                $result['sale'],
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(
        $id,
        Request $request,
        TenantContext $tenant,
        SaleTransactionService $sales
    ) {
        $detail = SaleDetail::with([
            'sale.customer',
        ])->find($id);

        if (!$detail) {
            return response()->json([
                'message' => 'Detalle de venta no encontrado',
            ], 404);
        }

        if (!$detail->sale) {
            return response()->json([
                'message' =>
                    'La venta asociada al detalle no existe',
            ], 422);
        }

        $companyId = $this->resolveCompanyId(
            $request,
            $tenant,
            $detail->sale
        );

        try {
            $sales->removeDetail(
                $detail,
                $companyId,
                (int) $request->user()->id
            );

            return response()->json(null, 204);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveCompanyId(
        Request $request,
        TenantContext $tenant,
        Sale $sale
    ): int {
        $saleCompanyId = $sale->company_id;

        if (
            $saleCompanyId === null
            && $sale->customer
        ) {
            $saleCompanyId =
                $sale->customer->company_id;
        }

        if ($saleCompanyId === null) {
            abort(
                422,
                'No se puede determinar la empresa de la venta.'
            );
        }

        return (int) $tenant->resolveCompanyId(
            $request->user(),
            $saleCompanyId,
            true
        );
    }
}
