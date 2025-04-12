<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cost;

class CostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $costs = Cost::with('batch')->get();
        return response()->json($costs);
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
            'description' => 'nullable|string|max:255',
            'batch_id' => 'required|exists:batches,id',
        ]);

        $cost = Cost::create([
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'batch_id' => $request->input('batch_id'),
        ]);

        return response()->json($cost, 201);
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
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'batch_id' => 'required|exists:batches,id',
        ]);

        $cost = Cost::find($id);
        if (!$cost) {
            return response()->json(['message' => 'Cost not found'], 404);
        }

        $cost->update([
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'batch_id' => $request->input('batch_id'),
        ]);

        return response()->json($cost, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $cost = Cost::find($id);
        if (!$cost) {
            return response()->json(['message' => 'Cost not found'], 404);
        }

        $cost->delete();

        return response()->json(['message' => 'Cost deleted successfully'], 200);
    }
}
