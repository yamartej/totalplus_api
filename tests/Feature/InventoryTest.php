<?php

namespace Tests\Feature;
// tests/Unit/InventoryTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\User;
use App\Models\Inventory;
use Database\Factories\InventoryFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_inventory()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        Inventory::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->get('/api/inventory');

        //$response = $this->get('/api/products');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'product_id',
                'quantity',
                'created_at',
                'updated_at',
            ],
        ]);
    }


    public function test_create_inventory()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Creamos un producto primero para obtener su ID
        $product = \App\Models\Product::factory()->create();

        // Datos para la creación del inventario
        $data = [
            'product_id' => $product->id, // Asegúrate de proporcionar un product_id válido
            'quantity' => 10,
        ];

        //$response = $this->postJson('/api/inventory', $data);
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/inventory', $data);

        $response->assertStatus(201)
            ->assertJson([
                'product_id' => $product->id,
                'quantity' => 10,
            ]);
    }

    public function test_get_inventory()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $product = \App\Models\Product::factory()->create();

        $inventory = Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity'  => '20',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/inventory/' . $inventory->id);

        $response->assertStatus(200);

        $response->assertJsonStructure();
    }

    public function test_put_inventory()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Creamos un producto primero para obtener su ID
        $product = \App\Models\Product::factory()->create();

        $inventory = Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity'  => '20',
        ]);

        // Datos para la creación del inventario
        $data = [
            'quantity' => 10,
        ];


        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->putJson('/api/inventory/' . $inventory->id, $data);
        $response->assertStatus(200);

        $response->assertJson($data);
    }
    public function test_delete_inventory()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $product = \App\Models\Product::factory()->create();

        $inventory = Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity'  => '20',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->deleteJson('/api/inventory/' . $inventory->id);


        $response->assertStatus(204);

        $this->assertDatabaseMissing('inventories', ['id' => $inventory->id]);
    }
}
