<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Response;


class UserTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_all_users()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        User::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->get('/api/users');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'roles',
                'created_at',
                'updated_at',
            ],
        ]);
    }
    public function  test_store_user()
    {
        $user = User::factory()->create(); // Simulate authenticated user
        $token = $user->createToken('test-token')->plainTextToken;

        $data = [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => '123456789', // Include password if required
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/users', $data);

        $response->assertStatus(Response::HTTP_CREATED); // Assert 201 status code
        $response->assertJsonStructure([
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
        ]); // Ensure proper JSON structure
    }
    public function test_show_user()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/users/' . $user->id);

        $response->assertStatus(200);

        $response->assertJsonStructure();
    }
    public function test_update_user()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $data = [
            'name' => $this->faker->name(),
        ];

        $response = $this->putJson('/api/users/' . $user->id, $data);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/users/' . $user->id, $data);

        $response->assertStatus(200);

        $response->assertJsonStructure();
    }

    public function test_delete_user()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->deleteJson('/api/users/' . $user->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
