<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use Database\Factories\ProductFactory; // Importa el Factory correcto
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product()
    {
        $product = ProductFactory::new()->create(); // Utiliza el Factory correctamente
        $data = [
            'name' => 'Producto de ejemplo',
            'description' => 'Esta es una descripción de ejemplo',
            'price' => 19.99,
            'image' => 'https://ejemplo.com/imagen.jpg',
            'category' => 'Electrónicos',
        ];
    
        $response = $this->postJson('/api/products', $data);
    
        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Producto de ejemplo',
                'description' => 'Esta es una descripción de ejemplo',
                'price' => 19.99,
                'image' => 'https://ejemplo.com/imagen.jpg',
                'category' => 'Electrónicos',
            ]);
    }

    public function test_get_product()
    {
        // Crear un producto de prueba en la base de datos usando el factory
        $product = Product::factory()->create([
            'name' => 'Producto de prueba',
            'price' => 50.00,
        ]);

        // Hacer la solicitud GET a la ruta para obtener el producto
        $response = $this->getJson('/api/products/' . $product->id);

        // Verificar que se haya obtenido el producto con éxito y que la respuesta sea 200 (OK)
        $response->assertStatus(200);

        // Verificar que los datos del producto obtenido coinciden con los datos del producto de prueba
        $response->assertJson([
            'id' => $product->id,
            'name' => 'Producto de prueba',
            'price' => 50.00,
        ]);
    }

    // Resto de tus pruebas...
}
