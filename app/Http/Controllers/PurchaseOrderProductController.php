<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderProduct;
use App\Models\PurchaseReceipt;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PurchaseOrderProductController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = PurchaseOrderProduct::query();

        $this->scopeLinesForCompany(
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
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $purchaseOrder = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->find($request->input('purchase_order_id'));

        if (!$purchaseOrder) {
            return response()->json([
                'message' => 'Orden de compra no encontrada',
            ], 404);
        }

        if ($this->hasReceipt((int) $purchaseOrder->id)) {
            return $this->immutableResponse();
        }

        $product = Product::query()
            ->where(function (Builder $query) use ($companyId) {
                $query
                    ->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->find($request->input('product_id'));

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $line = PurchaseOrderProduct::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'price' => $request->input('price'),
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json($line, 201);
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

        $query = PurchaseOrderProduct::query();

        $this->scopeLinesForCompany(
            $query,
            $companyId
        );

        $line = $query->find($id);

        if (!$line) {
            return response()->json([
                'message' => 'Producto de Orden de compra no encontrada',
            ], 404);
        }

        return response()->json($line, 200);
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

        $line = PurchaseOrderProduct::query()
            ->whereHas(
                'purchaseOrder',
                function (Builder $order) use ($companyId) {
                    $order->where('company_id', $companyId);
                }
            )
            ->find($id);

        if (!$line) {
            return response()->json([
                'message' => 'Orden de compra no encontrada',
            ], 404);
        }

        if ($this->hasReceipt((int) $line->purchase_order_id)) {
            return $this->immutableResponse();
        }

        $request->validate([
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        $line->update([
            'price' => $request->input('price'),
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json($line, 200);
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

        $line = PurchaseOrderProduct::query()
            ->whereHas(
                'purchaseOrder',
                function (Builder $order) use ($companyId) {
                    $order->where('company_id', $companyId);
                }
            )
            ->find($id);

        if (!$line) {
            return response()->json([
                'message' => 'Orden de compra no encontrada',
            ], 404);
        }

        if ($this->hasReceipt((int) $line->purchase_order_id)) {
            return $this->immutableResponse();
        }

        $line->delete();

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

    private function scopeLinesForCompany(
        Builder $query,
        ?int $companyId
    ): void {
        if ($companyId === null) {
            return;
        }

        $query->whereHas(
            'purchaseOrder',
            function (Builder $order) use ($companyId) {
                $order->where(function (Builder $scope) use ($companyId) {
                    $scope
                        ->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                });
            }
        );
    }
}
