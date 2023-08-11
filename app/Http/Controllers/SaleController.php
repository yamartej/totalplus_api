<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Sale;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::all();
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'customer_id' => 'required|exists:customers,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $sales = Sale::create([
            'product_id' => $request->input('product_id'),
            'customer_id' => $request->input('customer_id'),
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json($sales, 201);
    }

    public function show($id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrado'], 404);
        }

        return response()->json($sale, 200);
    }

    public function put(Request $request, $id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrado'], 404);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'customer_id' => 'required|exists:customers,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $sale->update([
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json($sale, 200);
    }

    public function destroy($id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrado'], 404);
        }

        $sale->delete();

        return response()->json(null, 204);
    }
}
