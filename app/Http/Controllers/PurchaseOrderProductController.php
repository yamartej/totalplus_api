<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\PurchaseOrderProduct;

class PurchaseOrderProductController extends Controller
{
    public function index()
    {
        $purchase_order_products = PurchaseOrderProduct::all();
        return response()->json($purchase_order_products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
        ]);

        $purchase_order_products = PurchaseOrderProduct::create([
            'purchase_order_id' => $request->input('purchase_order_id'),
            'product_id' => $request->input('product_id'),
            'price' => $request->input('price'),
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json($purchase_order_products, 201);
    }

    public function show($id)
    {
        $purchase_order_products = PurchaseOrderProduct::find($id);

        if (!$purchase_order_products) {
            return response()->json(['message' => 'Producto de Orden de compra no encontrada'], 404);
        }

        return response()->json($purchase_order_products, 200);
    }

    public function put(Request $request, $id)
    {
        $purchase_order_products = PurchaseOrderProduct::find($id);

        if (!$purchase_order_products) {
            return response()->json(['message' => 'Orden de compra no encontrado'], 404);
        }

        /*$request->validate([
            'shipping_cost' => 'required|numeric|min:0',
        ]);*/

        $purchase_order_products->update([
            'price' => $request->input('price'),
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json($purchase_order_products, 200);
    }

    public function destroy($id)
    {
        $purchase_order_products = PurchaseOrderProduct::find($id);

        if (!$purchase_order_products) {
            return response()->json(['message' => 'Orden de compra no encontrado'], 404);
        }

        $purchase_order_products->delete();

        return response()->json(null, 204);
    }
}
