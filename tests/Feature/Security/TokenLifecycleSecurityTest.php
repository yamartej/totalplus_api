<?php

namespace Tests\Feature\Security;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenLifecycleSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'token-user@example.test',
            'password' => Hash::make('secret123'),
        ]);
    }

    private function login(User $user): array
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'expiration',
                'refresh_token',
                'refresh_expiration',
                'user',
                'roles',
            ])
            ->json();
    }

    private function accessTokenId(string $plainAccessToken): int
    {
        return (int) explode('|', $plainAccessToken, 2)[0];
    }

    public function test_login_issues_access_and_refresh_token_pair(): void
    {
        $user = $this->user();
        $payload = $this->login($user);

        $this->assertNotEmpty($payload['token']);
        $this->assertNotEmpty($payload['refresh_token']);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $payload['refresh_token']),
            'revoked_at' => null,
        ]);
    }

    public function test_refresh_does_not_require_a_live_access_token(): void
    {
        $user = $this->user();
        $payload = $this->login($user);

        PersonalAccessToken::whereKey(
            $this->accessTokenId($payload['token'])
        )->delete();

        $this->postJson('/api/refresh-token', [
            'refresh_token' => $payload['refresh_token'],
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'expiration',
                'refresh_token',
                'refresh_expiration',
            ]);
    }

    public function test_refresh_rotates_token_and_replay_is_rejected(): void
    {
        $user = $this->user();
        $payload = $this->login($user);

        $rotated = $this->postJson('/api/refresh-token', [
            'refresh_token' => $payload['refresh_token'],
        ])->assertOk()->json();

        $this->assertNotSame(
            $payload['refresh_token'],
            $rotated['refresh_token']
        );

        $this->postJson('/api/refresh-token', [
            'refresh_token' => $payload['refresh_token'],
        ])->assertStatus(401);
    }

    public function test_rotation_revokes_associated_old_access_token(): void
    {
        $user = $this->user();
        $payload = $this->login($user);

        $oldAccessId = $this->accessTokenId($payload['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $oldAccessId,
        ]);

        $this->postJson('/api/refresh-token', [
            'refresh_token' => $payload['refresh_token'],
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldAccessId,
        ]);
    }

    public function test_expired_refresh_token_is_rejected(): void
    {
        $user = $this->user();
        $payload = $this->login($user);

        RefreshToken::where(
            'token_hash',
            hash('sha256', $payload['refresh_token'])
        )->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/refresh-token', [
            'refresh_token' => $payload['refresh_token'],
        ])->assertStatus(401);
    }

    public function test_logout_revokes_current_access_and_refresh_token(): void
    {
        $user = $this->user();
        $payload = $this->login($user);

        $accessId = $this->accessTokenId($payload['token']);
        $refreshHash = hash('sha256', $payload['refresh_token']);

        $this->withHeader(
            'Authorization',
            'Bearer ' . $payload['token']
        )->postJson('/api/logout', [
            'refresh_token' => $payload['refresh_token'],
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $accessId,
        ]);

        $this->assertNotNull(
            RefreshToken::where(
                'token_hash',
                $refreshHash
            )->value('revoked_at')
        );
    }

    public function test_logout_does_not_change_refresh_expiration(): void
    {
        $user = $this->user();
        $payload = $this->login($user);

        $refreshHash = hash('sha256', $payload['refresh_token']);

        $before = RefreshToken::where(
            'token_hash',
            $refreshHash
        )->firstOrFail();

        $expectedExpiration = $before->expires_at->format('Y-m-d H:i:s');

        $this->withHeader(
            'Authorization',
            'Bearer ' . $payload['token']
        )->postJson('/api/logout', [
            'refresh_token' => $payload['refresh_token'],
        ])->assertOk();

        $after = RefreshToken::where(
            'token_hash',
            $refreshHash
        )->firstOrFail();

        $this->assertSame(
            $expectedExpiration,
            $after->expires_at->format('Y-m-d H:i:s')
        );

        $this->assertNotNull($after->revoked_at);
    }
}
