<?php

namespace App\Http\Controllers;

use App\Models\CreditDetail;
use Illuminate\Http\Request;

class CreditDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $creditDetail = CreditDetail::all();
        return response()->json($creditDetail);
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

        $creditDetail = CreditDetail::create([
            'sale_id' => $request->input('sale_id'),
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
        $creditDetail = CreditDetail::find($id);

        if (!$creditDetail) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        $creditDetail->delete();

        return response()->json(['message' => 'Pago eliminado correctamente'], 200);
    }
}
