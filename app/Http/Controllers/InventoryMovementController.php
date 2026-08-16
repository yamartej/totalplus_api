<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryMovementController extends Controller
{
    private TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function index(Request $request)
    {
        $request->validate([
            'company_id' => 'sometimes|nullable|integer|exists:companies,id',
            'warehouse_id' => 'sometimes|nullable|integer|exists:warehouses,id',
            'product_id' => 'sometimes|nullable|integer|exists:products,id',
            'type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    InventoryMovement::TYPE_OPENING,
                    InventoryMovement::TYPE_RECEIVE,
                    InventoryMovement::TYPE_ISSUE,
                    InventoryMovement::TYPE_ADJUSTMENT,
                    InventoryMovement::TYPE_TRANSFER_IN,
                    InventoryMovement::TYPE_TRANSFER_OUT,
                ]),
            ],
            'from_date' => 'sometimes|nullable|date',
            'to_date' => 'sometimes|nullable|date|after_or_equal:from_date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $companyId = $this->tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = InventoryMovement::query()
            ->with([
                'product',
                'warehouse',
                'user',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($request->filled('warehouse_id')) {
            $query->where(
                'warehouse_id',
                (int) $request->query('warehouse_id')
            );
        }

        if ($request->filled('product_id')) {
            $query->where(
                'product_id',
                (int) $request->query('product_id')
            );
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->query('from_date')
            );
        }

        if ($request->filled('to_date')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->query('to_date')
            );
        }

        return response()->json(
            $query->paginate((int) $request->query('per_page', 25))
        );
    }
}
