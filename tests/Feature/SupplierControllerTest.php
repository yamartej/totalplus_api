<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Http\Controllers\SupplierController;
use App\Models\Supplier;
use Illuminate\Http\Response;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker; // Agregar el trait WithFaker
    
    public function test_index_returns_suppliers()
    {
        Supplier::factory()->count(3)->create();

        $response = $this->get('/api/suppliers');


        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'phone',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_store_creates_supplier()
    {
        // Crear un proveedor de prueba en la base de datos usando el factory
        $supplier = [
            'name' => $this->faker->name, // Genera un nombre aleatorio
            'phone' => $this->faker->phoneNumber, // Genera un número de teléfono aleatorio
        ];

        //$supplierData = ['name' => 'Supplier Name', 'phone' => '123-456-7890'];

        $response = $this->post('/api/suppliers', $supplier);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('suppliers', $supplier);
    }

    public function test_put_supplier()
    {
        $supplier = Supplier::factory()->create([
            'name' => $this->faker->name, // Genera un nombre aleatorio
            'phone' => $this->faker->phoneNumber, // Genera un número de teléfono aleatorio
        ]);

        $supplierData = ['name' => 'Otro Nombre', 'phone' => '123-456-7890'];

        $response = $this->putJson('/api/suppliers/'. $supplier->id, $supplierData );
        $response->assertStatus(200);
 
         $response->assertJson([
            'id' => $supplier->id,
            'name' => $supplierData['name'],
            'phone' => $supplierData['phone'],
         ]);

    }

    public function test_show_supplier()
    {
        $supplier = Supplier::factory()->create([
            'name' => $this->faker->name, // Genera un nombre aleatorio
            'phone' => $this->faker->phoneNumber, // Genera un número de teléfono aleatorio
        ]);

        $response = $this->getJson('/api/suppliers/'. $supplier->id);

        $response->assertStatus(200);
 
         // Verificar que los datos del cliente obtenido coinciden con los datos del cliente de prueba
         $response->assertJson([
             'id' => $supplier->id,
             'name' => $supplier->name,
             'phone' => $supplier->phone,
         ]);

    }

    public function test_delete_supplier()
    {
        $supplier = Supplier::factory()->create();
        
        $response = $this->deleteJson('/api/suppliers/'. $supplier->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
