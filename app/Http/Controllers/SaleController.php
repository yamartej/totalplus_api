<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\PointOfSale;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\Sales\SaleTransactionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    private function requestedCompanyId(Request $request)
    {
        if ($request->has('company_id')) {
            return $request->input('company_id');
        }

        return $request->query('company_id');
    }

    private function scopeSales(
        Builder $query,
        Request $request,
        TenantContext $tenant,
        $requestedCompanyId = null
    ): array {
        if ($requestedCompanyId === null) {
            $requestedCompanyId = $this->requestedCompanyId($request);
        }

        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $requestedCompanyId
        );

        if ($companyId !== null) {
            $query->where(function (Builder $saleQuery) use ($companyId) {
                $saleQuery
                    ->where('sales.company_id', $companyId)
                    ->orWhere(function (Builder $legacy) use ($companyId) {
                        $legacy
                            ->whereNull('sales.company_id')
                            ->whereHas(
                                'customer',
                                function (Builder $customer) use ($companyId) {
                                    $customer->where(
                                        'company_id',
                                        $companyId
                                    );
                                }
                            );
                    });
            });
        }

        return [$query, $companyId];
    }

    private function customerForCompany(
        int $customerId,
        int $companyId
    ): ?Customer {
        return Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->first();
    }

    private function sellerForCompany(
        int $sellerId,
        int $companyId
    ): ?User {
        return User::where('id', $sellerId)
            ->where('company_id', $companyId)
            ->first();
    }

    private function popForCompany(
        int $popId,
        int $companyId
    ): ?PointOfSale {
        return PointOfSale::where('id', $popId)
            ->where('company_id', $companyId)
            ->first();
    }

    private function inventoryForCompany(
        int $productId,
        int $companyId
    ): ?Inventory {
        return Inventory::where('product_id', $productId)
            ->whereHas(
                'warehouse',
                function (Builder $warehouse) use ($companyId) {
                    $warehouse->where('company_id', $companyId);
                }
            )
            ->first();
    }

    public function index(Request $request, TenantContext $tenant)
    {
        [$query] = $this->scopeSales(
            Sale::with([
                'customer',
                'details.product.inventory',
                'paymentDetails',
            ])->orderBy('created_at', 'desc'),
            $request,
            $tenant
        );

        return response()->json($query->get());
    }

    public function store(
        Request $request,
        TenantContext $tenant,
        SaleTransactionService $sales
    ) {
        $request->validate([
            'client_id' => 'required|integer',
            'seller_id' => 'required|integer',
            'pop_id' => 'required|integer',
            'total' => 'sometimes|numeric',
            'carts' => 'required|array|min:1',
            'carts.*.productId' => 'required|exists:products,id',
            'carts.*.quantity' => 'required|integer|min:1',
            'carts.*.warehouse_id' =>
                'sometimes|nullable|integer|exists:warehouses,id',
            'type_of_sale' => 'required|in:normal,credit',
        ]);

        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $customer = $this->customerForCompany(
            (int) $request->input('client_id'),
            $companyId
        );

        if (!$customer) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $seller = $this->sellerForCompany(
            (int) $request->input('seller_id'),
            $companyId
        );

        if (!$seller) {
            return response()->json([
                'message' => 'Vendedor no encontrado',
            ], 404);
        }

        $pop = $this->popForCompany(
            (int) $request->input('pop_id'),
            $companyId
        );

        if (!$pop) {
            return response()->json([
                'message' => 'Punto de venta no encontrado',
            ], 404);
        }

        try {
            $sale = $sales->create(
                $companyId,
                $customer,
                $seller,
                $pop,
                $request->input('type_of_sale'),
                $request->input('carts'),
                (int) $request->user()->id
            );

            return response()->json($sale, 201);
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
    public function show(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        [$query] = $this->scopeSales(
            Sale::where('id', $id),
            $request,
            $tenant
        );

        $sale = $query->first();

        if (!$sale) {
            return response()->json([
                'message' => 'Venta no encontrado',
            ], 404);
        }

        return response()->json($sale, 200);
    }

    public function put(
        Request $request,
        $id,
        TenantContext $tenant
    ) {
        [$query] = $this->scopeSales(
            Sale::where('id', $id),
            $request,
            $tenant
        );

        $sale = $query->first();

        if (!$sale) {
            return response()->json([
                'message' => 'Venta no encontrado',
            ], 404);
        }

        $sale->update([
            'total_amount' => $request->input('total_amount'),
            'credit_note_detail' =>
                $request->input('credit_note_detail'),
            'credit_note_date' =>
                $request->input('credit_note_date', now()),
        ]);

        return response()->json($sale, 200);
    }

    public function destroy(
        $id,
        Request $request,
        TenantContext $tenant,
        SaleTransactionService $sales
    ) {
        [$query, $companyId] = $this->scopeSales(
            Sale::where('id', $id),
            $request,
            $tenant
        );

        $sale = $query->first();

        if (!$sale) {
            return response()->json([
                'message' => 'Venta no encontrado',
            ], 404);
        }

        if ($companyId === null) {
            $companyId = $sale->company_id
                ?? optional($sale->customer)->company_id;
        }

        if ($companyId === null) {
            return response()->json([
                'message' =>
                    'No se puede determinar la empresa de la venta.',
            ], 422);
        }

        try {
            $sales->void(
                $sale,
                (int) $companyId,
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
    public function getSalesByCreditType(
        Request $request,
        TenantContext $tenant
    ) {
        [$query] = $this->scopeSales(
            Sale::with([
                'customer',
                'details.product',
                'paymentDetails',
            ])
                ->where('type_of_sale', 'credit')
                ->orderBy('created_at', 'desc'),
            $request,
            $tenant
        );

        return response()->json($query->get());
    }

    public function getCreditByCustomers(
        Request $request,
        TenantContext $tenant
    ) {
        [$query] = $this->scopeSales(
            Sale::query()
                ->select(
                    'customer_id',
                    DB::raw('SUM(total_amount) as total_debt')
                )
                ->where('type_of_sale', 'credit')
                ->groupBy('customer_id'),
            $request,
            $tenant
        );

        $credits = $query->get();

        $result = $credits->map(function ($item) {
            $customer = Customer::find($item->customer_id);

            $payments = DB::table('credit_customer_details')
                ->where('customer_id', $item->customer_id)
                ->get();

            return [
                'customer_id' => $item->customer_id,
                'customer_name' =>
                    $customer ? $customer->name : null,
                'total_debt' => $item->total_debt,
                'payments' => $payments,
            ];
        });

        return response()->json($result);
    }

    public function creditCustomerRegister(
        Request $request,
        TenantContext $tenant
    ) {
        $request->validate([
            'customer_id' => 'required|integer',
            'amount' => 'required|numeric',
        ]);

        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $customer = $this->customerForCompany(
            (int) $request->input('customer_id'),
            $companyId
        );

        if (!$customer) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $popId = null;

        if ($request->filled('pop_id')) {
            $pop = $this->popForCompany(
                (int) $request->input('pop_id'),
                $companyId
            );

            if (!$pop) {
                return response()->json([
                    'message' => 'Punto de venta no encontrado',
                ], 404);
            }

            $popId = $pop->id;
        }

        $authenticatedUser = $request->user();

        $sellerId =
            (int) $authenticatedUser->company_id === $companyId
                ? $authenticatedUser->id
                : null;

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'seller_id' => $sellerId,
            'total_amount' => $request->input('amount'),
            'credit_note_date' =>
                $request->input('credit_note_date', now()),
            'credit_note_detail' =>
                $request->input('credit_note_detail', ''),
            'pop_id' => $popId,
            'type_of_sale' => 'credit',
            'company_id' => $companyId,
        ]);

        return response()->json($sale, 201);
    }

    public function getCreditNoteList(
        Request $request,
        TenantContext $tenant
    ) {
        [$query] = $this->scopeSales(
            Sale::with(['customer'])
                ->where('type_of_sale', 'credit')
                ->orderBy('created_at', 'desc'),
            $request,
            $tenant
        );

        $sales = $query->get()->map(function ($sale) {
            return [
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'customer_name' =>
                    $sale->customer ? $sale->customer->name : null,
                'total_amount' => $sale->total_amount,
                'credit_note_date' => $sale->credit_note_date,
                'credit_note_detail' => $sale->credit_note_detail,
            ];
        });

        return response()->json($sales);
    }

    public function getCreditNoteListByCompany(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $id
        );

        [$query] = $this->scopeSales(
            Sale::with(['customer'])
                ->where('type_of_sale', 'credit')
                ->orderBy('created_at', 'desc'),
            $request,
            $tenant,
            $companyId
        );

        $sales = $query->get()->map(function ($sale) {
            return [
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'customer_name' =>
                    $sale->customer ? $sale->customer->name : null,
                'total_amount' => $sale->total_amount,
                'credit_note_date' => $sale->credit_note_date,
                'credit_note_detail' => $sale->credit_note_detail,
            ];
        });

        return response()->json($sales);
    }

    public function getCreditByCustomersByCompany(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $id
        );

        [$query] = $this->scopeSales(
            Sale::query()
                ->select(
                    'customer_id',
                    DB::raw('SUM(total_amount) as total_debt')
                )
                ->where('type_of_sale', 'credit')
                ->groupBy('customer_id'),
            $request,
            $tenant,
            $companyId
        );

        $credits = $query->get();

        $result = $credits->map(function ($item) {
            $customer = Customer::find($item->customer_id);

            $payments = DB::table('credit_customer_details')
                ->where('customer_id', $item->customer_id)
                ->get();

            return [
                'customer_id' => $item->customer_id,
                'customer_name' =>
                    $customer ? $customer->name : null,
                'total_debt' => $item->total_debt,
                'payments' => $payments,
            ];
        });

        return response()->json($result);
    }

    public function getSalesByCompany(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $id
        );

        [$query] = $this->scopeSales(
            Sale::with([
                'customer',
                'details.product.inventory',
                'paymentDetails',
            ])->orderBy('created_at', 'desc'),
            $request,
            $tenant,
            $companyId
        );

        return response()->json($query->get());
    }
}
