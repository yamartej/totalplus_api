<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    /**
     * Report access must honor the same tenant rules used by sale listings.
     * Historical sales without company_id are scoped through their customer.
     */
    private function scopedSales(Request $request, TenantContext $tenant): Builder
    {
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        // These endpoints report active commercial operations.
        // Voided sales remain accessible through the audit/history listings.
        $query = Sale::query()->whereNull('sales.voided_at');

        if ($companyId !== null) {
            $query->where(function (Builder $sales) use ($companyId) {
                $sales->where('sales.company_id', $companyId)
                    ->orWhere(function (Builder $legacy) use ($companyId) {
                        $legacy->whereNull('sales.company_id')
                            ->whereHas('customer', function (Builder $customer) use ($companyId) {
                                $customer->where('company_id', $companyId);
                            });
                    });
            });
        }

        return $query;
    }

    public function salesByPeriod(
        $start_date,
        $end_date,
        Request $request,
        TenantContext $tenant
    ) {
        validator(
            ['start_date' => $start_date, 'end_date' => $end_date],
            [
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            ]
        )->validate();

        $sales = $this->scopedSales($request, $tenant)
            ->whereDate('sales.created_at', '>=', $start_date)
            ->whereDate('sales.created_at', '<=', $end_date)
            ->orderByDesc('sales.created_at')
            ->get();

        return response()->json($sales);
    }

    public function salesByCustomer(
        $customer_id,
        Request $request,
        TenantContext $tenant
    ) {
        validator(['customer_id' => $customer_id], [
            'customer_id' => 'required|integer|min:1',
        ])->validate();

        return response()->json(
            $this->scopedSales($request, $tenant)
                ->where('sales.customer_id', $customer_id)
                ->orderByDesc('sales.created_at')
                ->get()
        );
    }

    public function salesByProduct(
        $product_id,
        Request $request,
        TenantContext $tenant
    ) {
        validator(['product_id' => $product_id], [
            'product_id' => 'required|integer|min:1',
        ])->validate();

        // sales.product_id was removed in 2025. Products belong to sales_details.
        return response()->json(
            $this->scopedSales($request, $tenant)
                ->whereHas('details', function (Builder $details) use ($product_id) {
                    $details->where('product_id', $product_id);
                })
                ->with(['details' => function ($details) use ($product_id) {
                    $details->where('product_id', $product_id);
                }])
                ->orderByDesc('sales.created_at')
                ->get()
        );
    }
}
