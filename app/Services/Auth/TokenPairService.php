<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class TokenPairService
{
    public function issue(User $user): array
    {
        return DB::transaction(function () use ($user) {
            return $this->createPair($user);
        });
    }

    public function rotate(string $plainRefreshToken): ?array
    {
        $hash = hash('sha256', $plainRefreshToken);

        return DB::transaction(function () use ($hash) {
            $refresh = RefreshToken::where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (!$refresh) {
                return null;
            }

            if ($refresh->revoked_at !== null) {
                return null;
            }

            if ($refresh->expires_at->isPast()) {
                $refresh->revoked_at = now();
                $refresh->save();

                return null;
            }

            $user = User::find($refresh->user_id);

            if (!$user) {
                $refresh->revoked_at = now();
                $refresh->save();

                return null;
            }

            $oldAccessTokenId = $refresh->access_token_id;

            $refresh->revoked_at = now();
            $refresh->save();

            if ($oldAccessTokenId) {
                PersonalAccessToken::whereKey(
                    $oldAccessTokenId
                )->delete();
            }

            return $this->createPair($user);
        });
    }

    public function revokeRefreshToken(
        ?string $plainRefreshToken
    ): void {
        if (!$plainRefreshToken) {
            return;
        }

        $hash = hash('sha256', $plainRefreshToken);

        RefreshToken::where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);
    }

    public function revokeCurrentAccessToken(
        User $user,
        ?string $plainAccessToken = null
    ): void {
        /*
         * Sanctum can authenticate a stateful request through the web guard.
         * In that case currentAccessToken() is a TransientToken, which is not
         * a persisted personal_access_tokens row and cannot be deleted.
         *
         * Prefer the explicit Bearer token when one was sent.
         */
        if ($plainAccessToken) {
            $accessToken = PersonalAccessToken::findToken(
                $plainAccessToken
            );

            if (
                $accessToken &&
                (int) $accessToken->tokenable_id === (int) $user->id
            ) {
                $accessToken->delete();
            }

            return;
        }

        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }
    }

    private function createPair(User $user): array
    {
        $access = $user->createToken(
            'access-token',
            ['access']
        );

        $accessExpiration = now()->addMinutes(
            max(
                1,
                (int) config(
                    'auth_tokens.access_minutes',
                    15
                )
            )
        );

        $plainRefreshToken = Str::random(80);

        $refreshExpiration = now()->addDays(
            max(
                1,
                (int) config(
                    'auth_tokens.refresh_days',
                    7
                )
            )
        );

        RefreshToken::create([
            'user_id' => $user->id,
            'access_token_id' => $access->accessToken->id,
            'token_hash' =>
                hash('sha256', $plainRefreshToken),
            'expires_at' => $refreshExpiration,
        ]);

        return [
            'token' => $access->plainTextToken,
            'expiration' => $accessExpiration,
            'refresh_token' => $plainRefreshToken,
            'refresh_expiration' => $refreshExpiration,
        ];
    }
}
