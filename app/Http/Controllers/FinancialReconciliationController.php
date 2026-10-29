<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReconciliationController extends Controller
{
    /**
     * Read-only financial view. Payments linked to a sale and deposits linked
     * only to a customer are DIFFERENT ledgers and are never silently allocated.
     *
     * This report is a diagnostic view, not a legally certified balance.
     */
    public function index(Request $request, TenantContext $tenant)
    {
        $input = $request->validate([
            'company_id' => 'sometimes|nullable|integer|min:1',
            'customer_id' => 'sometimes|nullable|integer|min:1',
        ]);

        // Even global administrators must select ONE company. Prevents a
        // cross-company aggregate from becoming an implicit data export.
        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $input['company_id'] ?? null,
            true
        );

        $customerQuery = Customer::query()
            ->where('company_id', $companyId);

        if (isset($input['customer_id'])) {
            $customerQuery->whereKey($input['customer_id']);
            if (!$customerQuery->exists()) {
                return response()->json([
                    'message' => 'Cliente no encontrado en la empresa seleccionada.',
                ], 404);
            }
        }

        $customers = $customerQuery->orderBy('id')->get(['id', 'name']);
        $customerIds = $customers->pluck('id')->all();

        if (!$customerIds) {
            return response()->json([
                'company_id' => $companyId,
                'summary' => $this->summary([]),
                'customers' => [],
                'notice' => $this->notice(),
            ]);
        }

        $salesScope = function (Builder $query) use ($companyId, $customerIds) {
            return $query
                ->whereIn('sales.customer_id', $customerIds)
                ->where('sales.type_of_sale', 'credit')
                ->whereNull('sales.voided_at')
                ->where(function (Builder $company) use ($companyId) {
                    $company
                        ->where('sales.company_id', $companyId)
                        ->orWhereNull('sales.company_id'); // verified through owned customers
                });
        };

        $saleTotals = $salesScope(DB::table('sales'))
            ->selectRaw('sales.customer_id, COUNT(*) as sale_count, COALESCE(SUM(sales.total_amount),0) as credit_total')
            ->groupBy('sales.customer_id')
            ->get()
            ->keyBy('customer_id');

        // Payments are aggregated separately, avoiding multiplying sale totals
        // by the number of credit_details rows when a sale has many payments.
        $linkedPayments = DB::table('credit_details as credit')
            ->join('sales', 'sales.id', '=', 'credit.sale_id');
        $paymentTotals = $salesScope($linkedPayments)
            ->selectRaw('sales.customer_id, COALESCE(SUM(credit.amount),0) as paid_total')
            ->groupBy('sales.customer_id')
            ->pluck('paid_total', 'customer_id');

        $unallocatedPayments = DB::table('credit_customer_details')
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, COALESCE(SUM(amount),0) as amount_total')
            ->groupBy('customer_id')
            ->pluck('amount_total', 'customer_id');

        $rows = $customers->map(function (Customer $customer) use (
            $saleTotals,
            $paymentTotals,
            $unallocatedPayments
        ) {
            $sales = $saleTotals->get($customer->id);
            $charged = (float) ($sales->credit_total ?? 0);
            $paid = (float) ($paymentTotals->get($customer->id, 0));
            $unallocated = (float) ($unallocatedPayments->get($customer->id, 0));

            return [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'credit_sales_count' => (int) ($sales->sale_count ?? 0),
                'credit_sales_total' => $this->money($charged),
                'sale_payments_total' => $this->money($paid),
                'sale_balance' => $this->money($charged - $paid),
                'customer_payments_unallocated' => $this->money($unallocated),
            ];
        })->filter(function (array $row) {
            return $row['credit_sales_count'] > 0 ||
                $row['customer_payments_unallocated'] !== '0.00';
        })->values()->all();

        return response()->json([
            'company_id' => $companyId,
            'summary' => $this->summary($rows),
            'customers' => $rows,
            'notice' => $this->notice(),
        ]);
    }

    private function summary(array $rows): array
    {
        $charged = 0.0;
        $paid = 0.0;
        $open = 0.0;
        $unallocated = 0.0;
        $salesCount = 0;

        foreach ($rows as $row) {
            $charged += (float) $row['credit_sales_total'];
            $paid += (float) $row['sale_payments_total'];
            $open += (float) $row['sale_balance'];
            $unallocated += (float) $row['customer_payments_unallocated'];
            $salesCount += $row['credit_sales_count'];
        }

        return [
            'customers_count' => count($rows),
            'credit_sales_count' => $salesCount,
            'credit_sales_total' => $this->money($charged),
            'sale_payments_total' => $this->money($paid),
            'sale_balance' => $this->money($open),
            'customer_payments_unallocated' => $this->money($unallocated),
        ];
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    private function notice(): string
    {
        return 'Los abonos directos del cliente no están vinculados a ventas. ' .
            'Se muestran por separado y NO se descuentan automáticamente del saldo ' .
            'de ventas para evitar doble contabilización. Revisar asignación y conciliación manual.';
    }
}
