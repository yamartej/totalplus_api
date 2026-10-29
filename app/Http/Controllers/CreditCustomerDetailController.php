<?php

namespace App\Http\Controllers;

use App\Models\CreditCustomerDetail;
use App\Models\Customer;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CreditCustomerDetailController extends Controller
{
    private function customers(Request $request, TenantContext $tenant, $requestedCompanyId = null)
    {
        return $tenant->scope(
            Customer::query(),
            $request->user(),
            $requestedCompanyId !== null ? $requestedCompanyId : $request->input('company_id', $request->query('company_id'))
        );
    }

    private function details(Request $request, TenantContext $tenant, $companyId = null)
    {
        $customerIds = $this->customers($request, $tenant, $companyId)->select('customers.id');
        return CreditCustomerDetail::query()->whereIn('customer_id', $customerIds)->with('customer');
    }

    public function index(Request $request, TenantContext $tenant)
    {
        return response()->json($this->details($request, $tenant)->get());
    }

    public function store(Request $request, TenantContext $tenant)
    {
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'amount' => ['required', 'numeric', 'gt:0',
                'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'detail' => 'nullable|string|max:255',
            'payment_date' => 'required|date',
        ]);
        if (!$this->customers($request, $tenant)->whereKey($data['customer_id'])->exists()) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }
        return response()->json(CreditCustomerDetail::create($data), 201);
    }

    public function destroy($id, Request $request, TenantContext $tenant)
    {
        $detail = $this->details($request, $tenant)->whereKey($id)->first();
        if (!$detail) {
            return response()->json(['message' => 'Detalle de crédito no encontrado'], 404);
        }
        $detail->delete();
        return response()->json(null, 204);
    }

    public function paymentByDate(Request $request, TenantContext $tenant)
    {
        $request->validate([
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
        ]);
        $query = $this->details($request, $tenant);
        if ($request->filled('start_date')) {
            $query->whereDate('payment_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('payment_date', '<=', $request->input('end_date'));
        }
        if (!$request->filled('start_date') && !$request->filled('end_date')) {
            $query->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year);
        }
        return response()->json($query->get());
    }

    public function creditCustomerDetailsByCompany($id, Request $request, TenantContext $tenant)
    {
        return response()->json($this->details($request, $tenant, $id)->get());
    }
}
