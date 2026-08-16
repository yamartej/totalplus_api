<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\Auth\ProviderLoginVerifier;
use App\Services\Auth\TokenPairService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function login(
        Request $request,
        TokenPairService $tokens
    ) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only(
            'email',
            'password'
        );

        if (Auth::guard('web')->attempt($credentials)) {
            $user = Auth::guard('web')->user();
            $roles = $user->roles()->get();
            $pair = $tokens->issue($user);

            return response()->json(array_merge(
                $pair,
                [
                    'user' => $user,
                    'roles' => $roles,
                ]
            ));
        }

        throw ValidationException::withMessages([
            'email' => [
                'Las credenciales proporcionadas son incorrectas.',
            ],
        ]);
    }

    public function logout(
        Request $request,
        TokenPairService $tokens
    ) {
        $request->validate([
            'refresh_token' => 'nullable|string|max:255',
        ]);

        $tokens->revokeRefreshToken(
            $request->input('refresh_token')
        );

        $tokens->revokeCurrentAccessToken(
            $request->user(),
            $request->bearerToken()
        );

        /*
         * If this request was also authenticated through the web guard,
         * clear that local Laravel session without affecting other tokens.
         */
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    public function loginWithProvider(
        Request $request,
        ProviderLoginVerifier $verifier,
        TokenPairService $tokens
    ) {
        $validated = $request->validate([
            'email' => 'required|email',
            'provider' => 'required|string|max:30',
            'timestamp' => 'required|integer',
            'nonce' => 'required|string|max:100',
            'signature' => [
                'required',
                'string',
                'regex:/^[a-fA-F0-9]{64}$/',
            ],
        ]);

        $verified = $verifier->verify(
            $validated['email'],
            $validated['provider'],
            (int) $validated['timestamp'],
            $validated['nonce'],
            $validated['signature']
        );

        if (!$verified) {
            return response()->json([
                'message' =>
                    'Provider authentication assertion is invalid.',
            ], 401);
        }

        $user = User::where(
            'email',
            strtolower(trim($validated['email']))
        )->first();

        if (!$user) {
            return response()->json([
                'message' => 'Unable to authenticate user.',
            ], 401);
        }

        $roles = $user->roles()->get();
        $pair = $tokens->issue($user);

        return response()->json(array_merge(
            $pair,
            [
                'user' => $user,
                'roles' => $roles,
            ]
        ));
    }
}
