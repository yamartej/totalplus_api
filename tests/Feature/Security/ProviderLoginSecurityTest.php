<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProviderLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'phase-1-test-secret-that-is-long-enough';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth_bridge.secret' => self::SECRET,
            'auth_bridge.ttl' => 60,
            'auth_bridge.providers' => ['google', 'github', 'facebook'],
            'cache.default' => 'array',
        ]);

        Cache::flush();
    }

    private function signedPayload(
        string $email,
        string $provider = 'google',
        ?int $timestamp = null,
        ?string $nonce = null
    ): array {
        $timestamp = $timestamp ?? time();
        $nonce = $nonce ?? 'nonce-' . bin2hex(random_bytes(12));

        $normalizedEmail = strtolower(trim($email));
        $normalizedProvider = strtolower(trim($provider));

        $payload = implode('|', [
            $normalizedProvider,
            $normalizedEmail,
            (string) $timestamp,
            $nonce,
        ]);

        return [
            'email' => $email,
            'provider' => $provider,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => hash_hmac('sha256', $payload, self::SECRET),
        ];
    }

    public function test_provider_login_rejects_unsigned_email_only_request(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login-provider', [
            'email' => $user->email,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_provider_login_rejects_invalid_signature(): void
    {
        $user = User::factory()->create();

        $payload = $this->signedPayload($user->email);
        $payload['signature'] = str_repeat('0', 64);

        $response = $this->postJson('/api/login-provider', $payload);

        $response->assertStatus(401);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_provider_login_rejects_stale_assertion(): void
    {
        $user = User::factory()->create();

        $payload = $this->signedPayload(
            $user->email,
            'google',
            time() - 120
        );

        $response = $this->postJson('/api/login-provider', $payload);

        $response->assertStatus(401);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_provider_login_accepts_valid_server_assertion(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(
            '/api/login-provider',
            $this->signedPayload($user->email, 'github')
        );

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure([
                'token',
                'user',
                'expiration',
                'roles',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_provider_login_rejects_replayed_assertion(): void
    {
        $user = User::factory()->create();

        $payload = $this->signedPayload(
            $user->email,
            'facebook',
            time(),
            'fixed-replay-nonce'
        );

        $this->postJson('/api/login-provider', $payload)->assertOk();

        $this->postJson('/api/login-provider', $payload)->assertStatus(401);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
