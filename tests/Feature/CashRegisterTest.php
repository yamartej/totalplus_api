<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Http\Response;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function  test_all_cash_registers()
    {
        CashRegister::factory()->count(3)->create();

        $response = $this->get('/api/cashregisters');

        $response->assertStatus(200);

        $response->assertJsonStructure();

    }

    public function test_store_cash_register()
    {
        $warehouse = Warehouse::factory()->create();

        $cash_register = [
            'name' => $this->faker->word, 
            'description' => $this->faker->word, 
            'warehouse_id' => $warehouse->id, 
        ];

        $response = $this->post('/api/cashregisters', $cash_register);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonStructure();
    }

    public function test_show_cash_register()
    {
        $warehouse = Warehouse::factory()->create();
        $cash_register = CashRegister::factory()->create([
            'name' => $this->faker->word, 
            'description' => $this->faker->word,
            'warehouse_id' => $warehouse->id,
        ]);

        $response = $this->getJson('/api/cashregisters/'. $cash_register->id);

        $response->assertStatus(200);

        $response->assertJsonStructure();
 
    }

    public function test_put_cash_register()
    {
        $warehouse = Warehouse::factory()->create();
        $cash_register = CashRegister::factory()->create();

        $cash_register_data = ['name' => 'Otro Nombre', 'description' => 'Otra descripcion', 'warehouse_id' => $warehouse->id];

        $response = $this->putJson('/api/cashregisters/'. $cash_register->id, $cash_register_data );
        
        $response->assertStatus(200);
 
        $response->assertJsonStructure();
        
    }

    public function test_delete_cash_register()
    {
        $cash_register = CashRegister::factory()->create();
        
        $response = $this->deleteJson('/api/cashregisters/'. $cash_register->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('cash_registers', [
            'id' => $cash_register->id,
            'name' => $cash_register->name,
            'description'=> $cash_register->description,
            'warehouse_id' => $cash_register->warehouse_id,
            'created_at'=> $cash_register->created_at,
            'updated_at' => $cash_register->updated_at,
        ]);
    }

}
