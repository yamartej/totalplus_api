<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function checkEmail(Request $request)
    {
        // Validar que el email venga en la petición
        $request->validate([
            'email' => 'required|email'
        ]);

        // Buscar si el usuario con ese correo existe
        $user = User::where('email', $request->email)->first();


        if ($user) {
            return response()->json(['exists' => true], 200);
        } else {
            return response()->json(['exists' => false], 404);
        }
    }
}
