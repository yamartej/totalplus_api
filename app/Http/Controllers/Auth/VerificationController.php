<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\VerifiesEmails;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */
    

    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function verifyToken(Request $request)
    {
        $user = Auth::user(); // Obtiene el usuario autenticado

        if ($request->user()) {
            return response()->json(['valid' => true], 200); // El token es válido
        } else {
            return response()->json(['valid' => false], 401); // El token no es válido
        }
    }

    public function refreshToken(Request $request)
    {
        // Aquí verificas si el usuario tiene un token de renovación válido
        $user = Auth::user();


        if ($user) {
            // Llama a la función para generar un nuevo token
            return $this->generateToken($user);
        }

        return response()->json(['message' => 'Unauthenticated'], Response::HTTP_UNAUTHORIZED);
    }

    public function generateToken($user)
    {
        // Genera un nuevo token para el usuario y configura su expiración
        $token = $user->createToken('my-token-name')->plainTextToken;
        $expiration = now()->addMinutes(config('sanctum.expiration'));

        return response()->json([
            'token' => $token,
            'user' => $user,
            'expiration' => $expiration,
        ]);
    }

    
}
