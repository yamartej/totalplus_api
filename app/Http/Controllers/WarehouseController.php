<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $warehouses = $tenant
            ->scope(
                Warehouse::query()->with(['company']),
                $request->user(),
                $request->query('company_id')
            )
            ->get();

        return response()->json($warehouses);
    }

    public function store(Request $request, TenantContext $tenant)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $warehouse = Warehouse::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
            'company_id' => $companyId,
        ]);

        $warehouse->load('company');

        return response()->json($warehouse, 201);
    }

    public function show(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $warehouse = $tenant
            ->scope(
                Warehouse::where('id', $id),
                $request->user(),
                $request->query('company_id')
            )
            ->first();

        if (!$warehouse) {
            return response()->json([
                'message' => 'Almacen no encontrado',
            ], 404);
        }

        return response()->json($warehouse, 200);
    }

    public function put(
        Request $request,
        $id,
        TenantContext $tenant
    ) {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $warehouse = $tenant
            ->scope(
                Warehouse::where('id', $id),
                $request->user(),
                $request->input('company_id')
            )
            ->first();

        if (!$warehouse) {
            return response()->json([
                'message' => 'Almacen no encontrado',
            ], 404);
        }

        $warehouse->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
        ]);

        return response()->json($warehouse, 200);
    }

    public function destroy(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $warehouse = $tenant
            ->scope(
                Warehouse::where('id', $id),
                $request->user(),
                $request->input('company_id')
            )
            ->first();

        if (!$warehouse) {
            return response()->json([
                'message' => 'Almacen no encontrado',
            ], 404);
        }

        $warehouse->delete();

        return response()->json(null, 204);
    }

    public function getByCompany(
        $company_id,
        Request $request,
        TenantContext $tenant
    ) {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $company_id
        );

        $warehouses = Warehouse::where(
            'company_id',
            $companyId
        )->get();

        $warehouses->load('company');

        return response()->json($warehouses, 200);
    }
}
