<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Batch::query();
        $this->scopeReadableBatches($query, $companyId);

        return response()->json($query->get());
    }

    public function store(Request $request, TenantContext $tenantContext)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'status' => 'required|in:created,received',
            'order_creation_date' => 'required|date',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        if (Batch::where('name', $request->input('name'))->exists()) {
            return response()->json([
                'message' => 'Ya existe un lote con este nombre',
            ], 400);
        }

        $batch = Batch::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'quantity' => $request->input('quantity'),
            'status' => $request->input('status'),
            'order_creation_date' => $request->input('order_creation_date'),
        ]);

        $batch->company_id = $companyId;
        $batch->save();

        return response()->json($batch, 201);
    }

    public function show(Request $request, $id, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Batch::query();
        $this->scopeReadableBatches($query, $companyId);

        $batch = $query->find($id);

        if (!$batch) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        return response()->json($batch, 200);
    }

    public function update(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'status' => 'required|in:created,received',
            'order_creation_date' => 'required|date',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        $query = Batch::query();
        $this->scopeOwnedBatches($query, $companyId);

        $batch = $query->find($id);

        if (!$batch) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        $nameExists = Batch::where(
            'name',
            $request->input('name')
        )
            ->where('id', '<>', $batch->id)
            ->exists();

        if ($nameExists) {
            return response()->json([
                'message' => 'Ya existe un lote con este nombre',
            ], 400);
        }

        $batch->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'quantity' => $request->input('quantity'),
            'status' => $request->input('status'),
            'order_creation_date' => $request->input('order_creation_date'),
        ]);

        return response()->json($batch, 200);
    }

    public function destroy(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id'),
            true
        );

        $query = Batch::query();
        $this->scopeOwnedBatches($query, $companyId);

        $batch = $query->find($id);

        if (!$batch) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        $batch->delete();

        return response()->json([
            'message' => 'Lote eliminado correctamente',
        ], 200);
    }

    public function getBatchesReceived(
        Request $request,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Batch::query()
            ->where('status', 'received');

        $this->scopeReadableBatches($query, $companyId);

        return response()->json($query->get());
    }

    public function getBatchesWithProducts(
        Request $request,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Batch::query()
            ->with([
                'products' => function ($productQuery) use ($companyId) {
                    if ($companyId === null) {
                        return;
                    }

                    $productQuery->where(
                        function ($tenantQuery) use ($companyId) {
                            $tenantQuery
                                ->where('company_id', $companyId)
                                ->orWhereNull('company_id');
                        }
                    );
                },
            ]);

        $this->scopeReadableBatches($query, $companyId);

        return response()->json($query->get());
    }

    private function scopeReadableBatches(
        Builder $query,
        ?int $companyId
    ): Builder {
        if ($companyId === null) {
            return $query;
        }

        return $query->where(
            function (Builder $tenantQuery) use ($companyId) {
                $tenantQuery
                    ->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            }
        );
    }

    private function scopeOwnedBatches(
        Builder $query,
        ?int $companyId
    ): Builder {
        if ($companyId === null) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }
}
