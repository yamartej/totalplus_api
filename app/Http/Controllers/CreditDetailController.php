<?php

namespace App\Http\Controllers;

use App\Models\CreditDetail;
use App\Models\Sale;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditDetailController extends Controller
{
    private function sales(Request $request, TenantContext $tenant): Builder
    {
        $companyId = $tenant->resolveCompanyId($request->user(), $request->input('company_id', $request->query('company_id')));
        $query = Sale::query();
        if ($companyId !== null) {
            $query->where(function (Builder $sales) use ($companyId) {
                $sales->where('company_id', $companyId)
                    ->orWhere(function (Builder $legacy) use ($companyId) {
                        $legacy->whereNull('company_id')->whereHas('customer', function (Builder $customer) use ($companyId) {
                            $customer->where('company_id', $companyId);
                        });
                    });
            });
        }
        return $query;
    }

    public function index(Request $request, TenantContext $tenant)
    {
        $sales = $this->sales($request, $tenant)->select('sales.id');
        return response()->json(CreditDetail::whereIn('sale_id', $sales)->get());
    }

    public function store(Request $request, TenantContext $tenant)
    {
        $data = $request->validate([
            'sale_id' => 'required|integer|exists:sales,id',
            'amount' => 'required|numeric|gt:0',
            'detail' => 'nullable|string|max:255',
            'payment_date' => 'required|date',
        ]);
        return DB::transaction(function () use ($request, $tenant, $data) {
            $sale = $this->sales($request, $tenant)->whereKey($data['sale_id'])->lockForUpdate()->first();
            if (!$sale) {
                return response()->json(['message' => 'Venta no encontrada'], 404);
            }
            if ($sale->type_of_sale !== 'credit') {
                throw ValidationException::withMessages(['sale_id' => ['La venta no es a crédito.']]);
            }
            $paid = CreditDetail::where('sale_id', $sale->id)->sum('amount');
            if ((float) $data['amount'] > round((float) $sale->total_amount - (float) $paid, 2)) {
                throw ValidationException::withMessages(['amount' => ['El pago supera el saldo pendiente.']]);
            }
            return response()->json(CreditDetail::create($data), 201);
        }, 3);
    }

    public function destroy($id, Request $request, TenantContext $tenant)
    {
        return DB::transaction(function () use ($id, $request, $tenant) {
            $detail = CreditDetail::whereKey($id)->lockForUpdate()->first();
            if (!$detail || !$this->sales($request, $tenant)->whereKey($detail->sale_id)->exists()) {
                return response()->json(['message' => 'Pago no encontrado'], 404);
            }
            $detail->delete();
            return response()->json(null, 204);
        }, 3);
    }
}
