<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $purchase_orders = PurchaseOrder::all();
        return response()->json($purchase_orders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $purchase_orders = PurchaseOrder::create([
            'supplier_id' => $request->input('supplier_id'),
            'shipping_cost' => $request->input('shipping_cost'),
            'tracking_number' => $request->input('tracking_number'),
        ]);

        return response()->json($purchase_orders, 201);
    }

    public function show($id)
    {
        $purchase_order = PurchaseOrder::find($id);

        if (!$purchase_order) {
            return response()->json(['message' => 'Orden de compra no encontrada'], 404);
        }

        return response()->json($purchase_order, 200);
    }

    public function put(Request $request, $id)
    {
        $purchase_order = PurchaseOrder::find($id);

        if (!$purchase_order) {
            return response()->json(['message' => 'Orden de compra no encontrado'], 404);
        }

        $request->validate([
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $purchase_order->update([
            'shipping_cost' => $request->input('shipping_cost'),
            'tracking_number' => $request->input('tracking_number'),
        ]);

        return response()->json($purchase_order, 200);
    }

    public function destroy($id)
    {
        $purchase_order = PurchaseOrder::find($id);

        if (!$purchase_order) {
            return response()->json(['message' => 'Orden de compra no encontrado'], 404);
        }

        $purchase_order->delete();

        return response()->json(null, 204);
    }
}
