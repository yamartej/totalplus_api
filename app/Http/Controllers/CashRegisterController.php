<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashRegister;
use App\Http\Resources\CashRegisterResource;


class CashRegisterController extends Controller
{
    public function index()
    {
        $cash_registers = CashRegister::all();
        return CashRegisterResource::collection($cash_registers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $cash_register = CashRegister::create($data);

        return new CashRegisterResource($cash_register);
    }

    public function show(CashRegister $cash_register)
    {
        return new CashRegisterResource($cash_register);
    }

    public function put(Request $request, CashRegister $cash_register)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $cash_register->update($data);

        return new CashRegisterResource($cash_register);
    }

    public function destroy($id)
    {
        $cash_register = CashRegister::find($id);

        if (!$cash_register) {
            return response()->json(['message' => 'Caja Registradora no encontrado'], 404);
        }

        $cash_register->delete();

        return response()->json(null, 204);
    }
}
