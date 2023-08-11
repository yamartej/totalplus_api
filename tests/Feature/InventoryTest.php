<?php

namespace Tests\Feature;
// tests/Unit/InventoryTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_inventory()
    {
        // Creamos un producto primero para obtener su ID
        $product = \App\Models\Product::factory()->create();

        // Datos para la creación del inventario
        $data = [
            'product_id' => $product->id, // Asegúrate de proporcionar un product_id válido
            'quantity' => 10,
        ];

        $response = $this->postJson('/api/inventory', $data);

        $response->assertStatus(201)
                ->assertJson([
                    'product_id' => $product->id,
                    'quantity' => 10,
                ]);
    }

    public function test_update_inventory()
    {
        $inventory = Inventory::factory()->create();

        $data = [
            'quantity' => 15,
        ];

        $response = $this->putJson('/api/inventory/' . $inventory->id, $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $inventory->id,
                'quantity' => 15,
            ]);
    }

    public function test_delete_inventory()
    {
        $inventory = Inventory::factory()->create();

        $response = $this->deleteJson('/api/inventory/' . $inventory->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('inventories', ['id' => $inventory->id]);
    }
}
