<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $customers = $tenant
            ->scope(
                Customer::query()->with(['company']),
                $request->user(),
                $request->query('company_id')
            )
            ->get();

        return response()->json($customers);
    }

    public function store(Request $request, TenantContext $tenant)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required',
            'client_id' => 'required',
        ]);

        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $customerInfo = Customer::where(
            'client_id',
            $request->input('client_id')
        )
            ->where('company_id', $companyId)
            ->first();

        if ($customerInfo) {
            return response()->json([
                'message' => 'Cliente en sistema',
            ], 409);
        }

        $customer = Customer::create([
            'company_id' => $companyId,
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),
        ]);

        return response()->json($customer, 201);
    }

    /**
     * Preserve the legacy contract: {id} is client_id here.
     */
    public function show(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $query = Customer::where('client_id', $id);

        $customers = $tenant
            ->scope(
                $query,
                $request->user(),
                $request->input('company_id')
            )
            ->get();

        if ($customers->isEmpty()) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        return response()->json($customers, 200);
    }

    public function update(
        Request $request,
        $id,
        TenantContext $tenant
    ) {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required',
            'client_id' => 'required',
        ]);

        $query = Customer::where('id', $id);

        $customer = $tenant
            ->scope(
                $query,
                $request->user(),
                $request->input('company_id')
            )
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $customer->update([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),
        ]);

        return response()->json($customer, 200);
    }

    public function delete(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $query = Customer::where('id', $id);

        $customer = $tenant
            ->scope(
                $query,
                $request->user(),
                $request->input('company_id')
            )
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $customer->delete();

        return response()->json(null, 204);
    }

    public function searchByClientId(
        $client_id,
        Request $request,
        TenantContext $tenant
    ) {
        $customers = $tenant
            ->scope(
                Customer::where('client_id', $client_id),
                $request->user(),
                $request->query('company_id')
            )
            ->get();

        if ($customers->isEmpty()) {
            return response()->json([
                'message' => 'Clientes no encontrados',
            ], 404);
        }

        return response()->json($customers, 200);
    }

    public function getCustomersWithCreditsAndPayments(
        Request $request,
        TenantContext $tenant
    ) {
        $customers = $tenant
            ->scope(
                Customer::query()->with([
                    'creditCustomerDetails',
                    'sale',
                ]),
                $request->user(),
                $request->query('company_id')
            )
            ->get();

        return response()->json($customers, 200);
    }

    public function getCustomersByCompany(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $id
        );

        $customers = Customer::with(['company'])
            ->where('company_id', $companyId)
            ->get();

        return response()->json($customers, 200);
    }
}
