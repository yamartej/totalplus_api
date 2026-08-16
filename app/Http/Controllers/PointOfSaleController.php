<?php

namespace App\Http\Controllers;

use App\Models\PointOfSale;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class PointOfSaleController extends Controller
{
    private function tenantQuery(Request $request, TenantContext $tenant)
    {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $request->input('company_id')
        );

        $query = PointOfSale::query();

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return [$query, $companyId];
    }

    public function index(Request $request, TenantContext $tenant)
    {
        [$query] = $this->tenantQuery($request, $tenant);

        $pops = $query->with(['company'])->get();

        return response()->json($pops, 200);
    }

    public function store(Request $request, TenantContext $tenant)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'ubication' => 'required|string|max:255',
        ]);

        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $existing = PointOfSale::where(
            'identifier',
            $request->input('identifier')
        )
            ->where('company_id', $companyId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'El identificador ya existe',
            ], 400);
        }

        $pop = PointOfSale::create([
            'identifier' => $request->input('identifier'),
            'ubication' => $request->input('ubication'),
            'company_id' => $companyId,
        ]);

        $pop->load('company');

        return response()->json($pop, 201);
    }

    public function show(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        [$query] = $this->tenantQuery($request, $tenant);

        $pop = $query->where('id', $id)->first();

        if (!$pop) {
            return response()->json([
                'message' => 'Punto de venta no encontrado',
            ], 404);
        }

        return response()->json($pop, 200);
    }

    public function update(
        Request $request,
        $id,
        TenantContext $tenant
    ) {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'ubication' => 'required|string|max:255',
        ]);

        [$query, $companyId] = $this->tenantQuery(
            $request,
            $tenant
        );

        $pop = $query->where('id', $id)->first();

        if (!$pop) {
            return response()->json([
                'message' => 'Punto de venta no encontrado',
            ], 404);
        }

        $seller = null;
        $sellerId = null;

        if ($request->filled('seller_id')) {
            $sellerCompanyId = $companyId ?? $pop->company_id;

            $sellerUser = User::where(
                'id',
                $request->input('seller_id')
            )
                ->where('company_id', $sellerCompanyId)
                ->first();

            if (!$sellerUser) {
                return response()->json([
                    'message' => 'Vendedor no encontrado',
                ], 404);
            }

            $seller = $sellerUser->name;
            $sellerId = $sellerUser->id;
        }

        $pop->update([
            'identifier' => $request->input('identifier'),
            'ubication' => $request->input('ubication'),
            'status' => $request->input('status', $pop->status),
            'seller_id' => $sellerId,
            'seller' => $seller,
        ]);

        return response()->json($pop, 200);
    }

    public function destroy(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        [$query] = $this->tenantQuery($request, $tenant);

        $pop = $query->where('id', $id)->first();

        if (!$pop) {
            return response()->json([
                'message' => 'Punto de venta no encontrado',
            ], 404);
        }

        $pop->delete();

        return response()->json([
            'message' => 'Punto de venta eliminado',
        ], 200);
    }

    public function getBySellerId(
        $seller_id,
        Request $request,
        TenantContext $tenant
    ) {
        [$query] = $this->tenantQuery($request, $tenant);

        $pops = $query
            ->where('seller_id', $seller_id)
            ->get();

        return response()->json($pops, 200);
    }

    public function getByCompanyId(
        $company_id,
        Request $request,
        TenantContext $tenant
    ) {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $company_id
        );

        $pops = PointOfSale::with(['company'])
            ->where('company_id', $companyId)
            ->get();

        if ($pops->isEmpty()) {
            return response()->json([
                'message' =>
                    'No se encontraron puntos de venta para esta empresa',
            ], 404);
        }

        return response()->json($pops, 200);
    }
}
