<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CreditCustomerDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class CreditCustomerDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $creditDetails = CreditCustomerDetail::with('customer')->get();

        return response()->json($creditDetails);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'detail' => 'nullable|string|max:255',
            'payment_date' => 'required|date',
        ]);

        $creditDetail = CreditCustomerDetail::create([
            'customer_id' => $request->input('customer_id'),
            'amount' => $request->input('amount'),
            'payment_date' => $request->input('payment_date'),
            'detail' => $request->input('detail'),
        ]);

        return response()->json($creditDetail, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $creditDetail = CreditCustomerDetail::find($id);

        if (!$creditDetail) {
            return response()->json(['message' => 'Detalle de crédito no encontrado'], 404);
        }

        $creditDetail->delete();

        return response()->json(null, 204);
    }

    public function paymentByDate(Request $request)
    {
        // Tomar en cuenta varios escenarios de fechas
        // 1. Fecha de inicio y fin proporcionadas
        // 2. Solo fecha de inicio proporcionada (hasta la fecha actual)
        // 3. Solo fecha de fin proporcionada (desde el inicio del mes actual)
        // 4. Ninguna fecha proporcionada (usar el mes actual)
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $query = CreditCustomerDetail::with('customer');
        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        } else {
            // Si no se proporcionan fechas, usar el mes actual
            $query->whereMonth('payment_date', date('m'))
                ->whereYear('payment_date', date('Y'));
        }
        $creditDetails = $query->get();
        return response()->json($creditDetails);
    }
}
