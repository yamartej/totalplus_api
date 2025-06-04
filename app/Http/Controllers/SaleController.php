<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalesHistory;
use App\Models\Inventory;
use App\Models\SaleDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with(['customer', 'details.product', 'paymentDetails']) // Incluye detalles y cada producto
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:customers,id',
            'seller_id' => 'required|exists:users,id',
            'pop_id' => 'required|exists:point_of_sales,id',
            'total' => 'required|numeric',
            'carts' => 'required|array',
            'carts.*.productId' => 'required|exists:products,id',
            'carts.*.quantity' => 'required|integer|min:1',
            'type_of_sale' => 'required|in:normal,credit',
        ]);

        DB::beginTransaction();

        try {
            // Crear la venta
            $sale = Sale::create([
                'customer_id' => $request->input('client_id'),
                'seller_id' => $request->input('seller_id'),
                'pop_id' => $request->input('pop_id'),
                'total_amount' => $request->input('total'),
                'type_of_sale' => $request->input('type_of_sale'),
            ]);

            // Crear el historial de ventas
            foreach ($request->input('carts') as $cart) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $cart['productId'],
                    'quantity' => $cart['quantity'],
                ]);
            }

            // Actualizar el inventario
            foreach ($request->input('carts') as $cart) {
                $inventory = Inventory::where('product_id', $cart['productId'])->first();
                $inventory->quantity -= $cart['quantity'];
                $inventory->save();
            }

            DB::commit();

            return response()->json($sale, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

        $sale->update([
            'total_amount' => $request->input('total_amount'),
            'credit_note_detail' => $request->input('credit_note_detail'),
            'credit_note_date' => $request->input('credit_note_date', now()),
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

    public function getSalesByCreditType()
    {
        // Valida que el tipo sea 'credit' o 'normal'
        $type = 'credit';
        if (!in_array($type, ['credit', 'normal'])) {
            return response()->json(['error' => 'Tipo de venta inválido'], 400);
        }

        $sales = Sale::with(['customer', 'details.product', 'paymentDetails']) // Incluye detalles y cada producto
            ->where('type_of_sale', $type)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($sales);
    }

    public function getCreditByCustomers()
    {
        // Obtener la suma total de ventas tipo "credit" agrupadas por cliente
        $credits = DB::table('sales')
            ->select('customer_id', DB::raw('SUM(total_amount) as total_debt'))
            ->where('type_of_sale', 'credit')
            ->groupBy('customer_id')
            ->get();

        // Incluir información del cliente y sus pagos
        $result = $credits->map(function ($item) {
            $customer = \App\Models\Customer::find($item->customer_id);

            // Obtener los pagos del cliente desde credit_customer_details
            $payments = DB::table('credit_customer_details')
                ->where('customer_id', $item->customer_id)
                ->get();

            return [
                'customer_id' => $item->customer_id,
                'customer_name' => $customer ? $customer->name : null,
                'total_debt' => $item->total_debt,
                'payments' => $payments,
            ];
        });

        return response()->json($result);
    }
    public function creditCustomerRegister(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
        ]);

        $user = $request->user();

        $sale = Sale::create([
            'customer_id' => $request->input('customer_id'),
            'seller_id' => $user->id,
            'total_amount' => $request->input('amount'),
            'credit_note_date' => $request->input('credit_note_date', now()),
            'credit_note_detail' => $request->input('credit_note_detail', ''),
            'pop_id' => $request->input('pop_id', null),
            'type_of_sale' => 'credit',
        ]);

        return response()->json($sale, 201);
    }

    public function getCreditNoteList()
    {
        $type = 'credit';

        $sales = Sale::with(['customer']) // Incluye detalles y cada producto
            ->where('type_of_sale', $type)
            ->orderBy('created_at', 'desc')
            ->get();

        // Formatear la respuesta para incluir solo los campos necesarios
        $sales = $sales->map(function ($sale) {
            return [
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer ? $sale->customer->name : null,
                'total_amount' => $sale->total_amount,
                'credit_note_date' => $sale->credit_note_date,
                'credit_note_detail' => $sale->credit_note_detail,
            ];
        });

        return response()->json($sales);
    }
}
