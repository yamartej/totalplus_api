<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Cost;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CostController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Cost::query()->with('batch');
        $this->scopeReadableCosts($query, $companyId);

        return response()->json($query->get());
    }

    public function store(Request $request, TenantContext $tenantContext)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'batch_id' => 'required|integer',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        $batchQuery = Batch::query();

        if ($companyId !== null) {
            $batchQuery->where('company_id', $companyId);
        }

        $batch = $batchQuery->find(
            $request->input('batch_id')
        );

        if (!$batch) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        $cost = Cost::create([
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'batch_id' => $batch->id,
        ]);

        return response()->json($cost, 201);
    }

    public function show(Request $request, $id, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Cost::query()->with('batch');
        $this->scopeReadableCosts($query, $companyId);

        $cost = $query->find($id);

        if (!$cost) {
            return response()->json([
                'message' => 'Cost not found',
            ], 404);
        }

        return response()->json($cost, 200);
    }

    public function update(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'batch_id' => 'required|integer',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        $costQuery = Cost::query();
        $this->scopeOwnedCosts($costQuery, $companyId);

        $cost = $costQuery->find($id);

        if (!$cost) {
            return response()->json([
                'message' => 'Cost not found',
            ], 404);
        }

        $batchQuery = Batch::query();

        if ($companyId !== null) {
            $batchQuery->where('company_id', $companyId);
        }

        $batch = $batchQuery->find(
            $request->input('batch_id')
        );

        if (!$batch) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        $cost->update([
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'batch_id' => $batch->id,
        ]);

        return response()->json($cost, 200);
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

        $query = Cost::query();
        $this->scopeOwnedCosts($query, $companyId);

        $cost = $query->find($id);

        if (!$cost) {
            return response()->json([
                'message' => 'Cost not found',
            ], 404);
        }

        $cost->delete();

        return response()->json([
            'message' => 'Cost deleted successfully',
        ], 200);
    }

    private function scopeReadableCosts(
        Builder $query,
        ?int $companyId
    ): Builder {
        if ($companyId === null) {
            return $query;
        }

        return $query->whereHas(
            'batch',
            function ($batchQuery) use ($companyId) {
                $batchQuery->where(
                    function ($tenantQuery) use ($companyId) {
                        $tenantQuery
                            ->where('company_id', $companyId)
                            ->orWhereNull('company_id');
                    }
                );
            }
        );
    }

    private function scopeOwnedCosts(
        Builder $query,
        ?int $companyId
    ): Builder {
        if ($companyId === null) {
            return $query;
        }

        return $query->whereHas(
            'batch',
            function ($batchQuery) use ($companyId) {
                $batchQuery->where(
                    'company_id',
                    $companyId
                );
            }
        );
    }
}
