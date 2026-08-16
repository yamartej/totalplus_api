<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;

class ProviderLoginVerifier
{
    public function verify(
        string $email,
        string $provider,
        int $timestamp,
        string $nonce,
        string $signature
    ): bool {
        $secret = (string) config('auth_bridge.secret');
        $ttl = max(1, (int) config('auth_bridge.ttl', 60));
        $allowedProviders = (array) config('auth_bridge.providers', []);

        $provider = strtolower(trim($provider));
        $email = strtolower(trim($email));

        if ($secret === '') {
            return false;
        }

        if (!in_array($provider, $allowedProviders, true)) {
            return false;
        }

        if (abs(time() - $timestamp) > $ttl) {
            return false;
        }

        if ($nonce === '' || strlen($nonce) > 100) {
            return false;
        }

        $payload = implode('|', [
            $provider,
            $email,
            (string) $timestamp,
            $nonce,
        ]);

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, strtolower($signature))) {
            return false;
        }

        // Prevent replay of an otherwise valid signed assertion.
        $nonceKey = 'auth-bridge:nonce:' . hash('sha256', $nonce);

        return Cache::add(
            $nonceKey,
            true,
            now()->addSeconds($ttl * 2)
        );
    }
}
