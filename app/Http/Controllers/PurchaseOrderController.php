<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderProduct;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = PurchaseOrder::query();

        $this->scopePurchaseOrdersForCompany(
            $query,
            $companyId
        );

        return response()->json($query->get());
    }

    public function store(
        Request $request,
        TenantContext $tenantContext
    ) {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $supplier = Supplier::query()
            ->where('company_id', $companyId)
            ->find($request->input('supplier_id'));

        if (!$supplier) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'shipping_cost' => $request->input('shipping_cost'),
            'tracking_number' => $request->input('tracking_number'),
        ]);

        $purchaseOrder->company_id = $companyId;
        $purchaseOrder->save();

        return response()->json($purchaseOrder, 201);
    }

    /**
     * Atomically create a purchase order together with its priced lines.
     * This opt-in route leaves the existing legacy order/line API unchanged.
     */
    public function storeWithLines(Request $request, TenantContext $tenantContext)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'shipping_cost' => 'required|numeric|min:0',
            'tracking_number' => 'sometimes|nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer|distinct|exists:products,id',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.price' => 'required|numeric|min:0',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $supplier = Supplier::query()
            ->where('company_id', $companyId)
            ->find($request->input('supplier_id'));

        if (!$supplier) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $lines = $request->input('lines');
        $productIds = collect($lines)
            ->pluck('product_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $validCount = Product::query()
            ->whereIn('id', $productIds)
            ->where(function (Builder $query) use ($companyId) {
                $query
                    ->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->count();

        if ($validCount !== count($lines)) {
            return response()->json([
                'message' => 'Producto no encontrado para la empresa',
            ], 404);
        }

        $order = DB::transaction(function () use ($companyId, $supplier, $request, $lines) {
            $order = new PurchaseOrder([
                'supplier_id' => $supplier->id,
                'tracking_number' => $request->input('tracking_number'),
                'shipping_cost' => $request->input('shipping_cost'),
            ]);
            $order->company_id = $companyId;
            $order->save();

            foreach ($lines as $line) {
                PurchaseOrderProduct::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                ]);
            }

            return $order;
        }, 3);

        return response()->json(
            $order->load('lines'),
            201
        );
    }

    public function show(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = PurchaseOrder::query();

        $this->scopePurchaseOrdersForCompany(
            $query,
            $companyId
        );

        $purchaseOrder = $query->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'message' => 'Orden de compra no encontrada',
            ], 404);
        }

        return response()->json($purchaseOrder, 200);
    }

    public function put(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        $purchaseOrder = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'message' => 'Orden de compra no encontrada',
            ], 404);
        }

        if ($this->hasReceipt((int) $purchaseOrder->id)) {
            return $this->immutableResponse();
        }

        $request->validate([
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $purchaseOrder->update([
            'shipping_cost' => $request->input('shipping_cost'),
            'tracking_number' => $request->input('tracking_number'),
        ]);

        return response()->json($purchaseOrder, 200);
    }

    public function destroy(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        $purchaseOrder = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'message' => 'Orden de compra no encontrada',
            ], 404);
        }

        if ($this->hasReceipt((int) $purchaseOrder->id)) {
            return $this->immutableResponse();
        }

        $purchaseOrder->delete();

        return response()->json(null, 204);
    }

    private function hasReceipt(int $purchaseOrderId): bool
    {
        return PurchaseReceipt::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->exists();
    }

    private function immutableResponse()
    {
        return response()->json([
            'message' => 'La orden de compra ya fue recibida y es inmutable.',
        ], 409);
    }

    private function scopePurchaseOrdersForCompany(
        Builder $query,
        ?int $companyId
    ): void {
        if ($companyId === null) {
            return;
        }

        $query->where(function (Builder $builder) use ($companyId) {
            $builder
                ->where('company_id', $companyId)
                ->orWhereNull('company_id');
        });
    }
}
