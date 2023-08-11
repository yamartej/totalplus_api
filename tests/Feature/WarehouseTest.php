<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Http\Response;

class WarehouseTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_all_warehose()
    {
        Warehouse::factory()->count(3)->create();

        $response = $this->get('/api/warehouses');

        $response->assertStatus(200);

        $response->assertJsonStructure();
    }

    public function test_store_warehouse()
    {
        $category = [
            'name' => $this->faker->word, 
            'description' => $this->faker->word, 
            'address' => $this->faker->word, 
        ];

        $response = $this->post('/api/warehouses', $category);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonStructure();
        //$this->assertDatabaseHas('warehouses', $category);
    }

    public function test_show_warehouse()
    {
        $warehouse = Warehouse::factory()->create([
            'name' => $this->faker->word, 
            'description' => $this->faker->word,
            'address' => $this->faker->word,
        ]);

        $response = $this->getJson('/api/warehouses/'. $warehouse->id);

        $response->assertStatus(200);

        $response->assertJsonStructure();
 
        /*$response->assertJson([
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'description' => $warehouse->description,
            'address' => $warehouse->address,
         ]);*/
    }

    public function test_put_warehouse()
    {
        $warehouse = Warehouse::factory()->create();

        $warehouseData = ['name' => 'Otro Nombre', 'address' => 'Otra address'];

        $response = $this->putJson('/api/warehouses/'. $warehouse->id, $warehouseData );
        
        $response->assertStatus(200);
 
        $response->assertJsonStructure();
        /*
        $response->assertJson([
            'id' => $warehouse->id,
            'name' => $warehouseData['name'],
            'description' => $warehouseData['description'],
         ]);*/
    }

    public function test_delete_warehouse()
    {
        $warehouse = Warehouse::factory()->create();
        
        $response = $this->deleteJson('/api/warehouses/'. $warehouse->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('warehouses', [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'description'=> $warehouse->description,
            'address' => $warehouse->address,
            'created_at'=> $warehouse->created_at,
            'updated_at' => $warehouse->updated_at,
        ]);
    }

}
