<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Supplier::query();

        $this->scopeSuppliersForCompany(
            $query,
            $companyId
        );

        return response()->json($query->get());
    }

    public function store(
        Request $request,
        TenantContext $tenantContext
    ) {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $supplier = Supplier::create([
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
        ]);

        $supplier->company_id = $companyId;
        $supplier->save();

        return response()->json($supplier, 201);
    }

    public function show(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Supplier::query();

        $this->scopeSuppliersForCompany(
            $query,
            $companyId
        );

        $supplier = $query->find($id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        return response()->json($supplier, 200);
    }

    public function put(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        $supplier = Supplier::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $supplier->update([
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
        ]);

        return response()->json($supplier, 200);
    }

    public function destroy(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        $supplier = Supplier::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $hasReceivedOrder = PurchaseReceipt::query()
            ->whereHas(
                'purchaseOrder',
                function (Builder $order) use ($supplier, $companyId) {
                    $order
                        ->where('supplier_id', $supplier->id)
                        ->where('company_id', $companyId);
                }
            )
            ->exists();

        if ($hasReceivedOrder) {
            return response()->json([
                'message' => 'El proveedor tiene compras recibidas y no puede eliminarse.',
            ], 409);
        }

        $supplier->delete();

        return response()->json(null, 204);
    }

    private function scopeSuppliersForCompany(
        Builder $query,
        ?int $companyId
    ): void {
        if ($companyId === null) {
            return;
        }

        $query->where(function (Builder $builder) use ($companyId) {
            $builder
                ->where('company_id', $companyId)
                ->orWhereNull('company_id');
        });
    }
}
