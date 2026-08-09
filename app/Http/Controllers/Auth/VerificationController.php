<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\Auth\TokenPairService;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        /*
         * refreshToken must remain callable after the access token expires.
         * Authentication for the other API methods is enforced by routes.
         */
        $this->middleware('auth')->except(['refreshToken']);
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only(
            'verify',
            'resend'
        );
    }

    public function verifyToken(Request $request)
    {
        return response()->json([
            'valid' => $request->user() !== null,
        ], $request->user() ? 200 : 401);
    }

    public function refreshToken(
        Request $request,
        TokenPairService $tokens
    ) {
        $validated = $request->validate([
            'refresh_token' => 'required|string|max:255',
        ]);

        $pair = $tokens->rotate(
            $validated['refresh_token']
        );

        if (!$pair) {
            return response()->json([
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        return response()->json($pair);
    }
}
