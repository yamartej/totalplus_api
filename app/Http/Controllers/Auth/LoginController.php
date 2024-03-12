<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Response;
use Illuminate\Auth\Events\Verified;




class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
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
        $this->middleware('guest')->except('logout');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::guard('web')->attempt($credentials)) {
            $user = Auth::guard('web')->user();
            //Este metodo es el que ejecuta la verificación del usuario.
            //$user->markEmailAsVerified();
            if (!$user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => ['Tu correo electrónico no ha sido verificado. Por favor, verifica tu correo electrónico e intenta nuevamente.'],
                ]);
            }

            // Autenticación exitosa, generar token de autenticación con Sanctum
            $roles = $user->roles()->get();
            $token = $user->createToken('my-token-name')->plainTextToken;
            $expiration = now()->addMinutes(config('sanctum.expiration'));
            return response()->json([
                'token' => $token,
                'user' => $user,
                'expiration' => $expiration,
                'roles' => $roles,
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['Las credenciales proporcionadas son incorrectas.'],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
