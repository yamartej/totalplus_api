<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Customer;

class CustomerTest extends TestCase
{
    use RefreshDatabase, WithFaker; // Agregar el trait WithFaker

    public function test_create_customer()
    {
        $data = [
            'name' => $this->faker->name, // Genera un nombre aleatorio
            'address' => $this->faker->address, // Genera una dirección aleatoria
            'phone' => $this->faker->phoneNumber, // Genera un número de teléfono aleatorio
        ];

        $response = $this->postJson('/api/customers', $data);

        $response->assertStatus(201)
            ->assertJson($data);
    }

    public function test_show_customer()
    {
        // Crear un cliente de prueba en la base de datos usando el factory
        $customer = Customer::factory()->create([
            'name' => $this->faker->name, // Genera un nombre aleatorio
            'address' => $this->faker->address, // Genera una dirección aleatoria
            'phone' => $this->faker->phoneNumber, // Genera un número de teléfono aleatorio
        ]);

         // Hacer la solicitud GET a la ruta para obtener el cliente
         $response = $this->getJson('/api/customers/' . $customer->id);

         // Verificar que se haya obtenido el cliente con éxito y que la respuesta sea 200 (OK)
         $response->assertStatus(200);
 
         // Verificar que los datos del cliente obtenido coinciden con los datos del cliente de prueba
         $response->assertJson([
             'id' => $customer->id,
             'name' => $customer->name,
             'address' => $customer->address,
             'phone' => $customer->phone,
         ]);
    }

    public function test_update_customer(){
        
        $customer = Customer::factory()->create([
            'name' => $this->faker->name, // Genera un nombre aleatorio
            'address' => $this->faker->address, // Genera una dirección aleatoria
            'phone' => $this->faker->phoneNumber, // Genera un número de teléfono aleatorio
        ]);

        $data = [
            'name' => 'Pedro Perez',
            'address' => 'Otra dirección', 
            'phone' => 'El mismo Telefono', 
        ];

         $response = $this->putJson('/api/customers/' . $customer->id, $data);

         $response->assertStatus(200);
 
         $response->assertJson([
            'name' => 'Pedro Perez',
            'address' => 'Otra dirección', 
            'phone' => 'El mismo Telefono', 
         ]);
    }
    public function test_delete_customer()
    {
        $customer = Customer::factory()->create();

        $response = $this->deleteJson('/api/customers/' . $customer->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);

    }

    public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
