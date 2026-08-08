<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Auth\LoginController;

class TokenVerificationController extends Controller
{
    public function verifyToken(Request $request)
    {
        $user = Auth::user(); // Obtiene el usuario autenticado

        if ($user) {
            return Response::json(['valid' => true], 200); // El token es válido
        } else {
            return Response::json(['valid' => false], 401); // El token no es válido
        }
    }
    public function refreshToken(Request $request)
    {
        // Aquí verificas si el usuario tiene un token de renovación válido
        $user = Auth::user();


        if ($user) {
            // Llama a la función para generar un nuevo token
            file_put_contents("jairo.txt",  "response=" . print_r($user, true) . "\n", FILE_APPEND);
            return $this->generateToken($user);
        }
        file_put_contents("jairo1.txt",  "response=" . print_r($user, true) . "\n", FILE_APPEND);
        return response()->json(['message' => 'Unauthenticated'], Response::HTTP_UNAUTHORIZED);
    }

    public function generateToken($user)
    {
        // Genera un nuevo token para el usuario y configura su expiración
        $token = $user->createToken('my-token-name')->plainTextToken;
        $expiration = now()->addMinutes(config('sanctum.expiration'));
        $roles = $user->roles()->get();
        return response()->json([
            'token' => $token,
            'user' => $user,
            'expiration' => $expiration,
            'roles' => $roles,
        ]);
    }
}
