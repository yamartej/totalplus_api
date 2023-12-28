<?php

namespace Tests\Unit;

use App\Models\Category;
use Tests\TestCase;
use App\Models\Product;
use App\Models\User;
use Database\Factories\ProductFactory; // Importa el Factory correcto
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;

class ProductTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_all_productos()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        Product::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->get('/api/products');

        //$response = $this->get('/api/products');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'description',
                'price',
                'category_id',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_create_product()
    {
        /*$category = Category::factory()->create();
        $product = [
            'name' => $this->faker->word, 
            'description' => $this->faker->paragraph,
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'image' => $this->faker->imageUrl(),
            'category_id' => $category->id,
        ];

        $response = $this->post('/api/products', $product);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('products', $product);*/

        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $category = Category::factory()->create();

        ProductFactory::new()->create(); // Utiliza el Factory correctamente
        $data = [
            'name' => 'Producto de ejemplo',
            'description' => 'Esta es una descripción de ejemplo',
            'price' => 19.99,
            'image' => 'https://ejemplo.com/imagen.jpg',
            'category_id' => $category->id,
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/products', $data);

        //$response = $this->postJson('/api/products/', $data);

        $response->assertStatus(201)
            ->assertJson($data);
    }

    public function test_get_product()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Crear un producto de prueba en la base de datos usando el factory
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Producto de prueba',
            'price' => 50.00,
            'category_id' => $category->id,
        ]);

        // Hacer la solicitud GET a la ruta para obtener el producto
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/products/' . $product->id);

        //$response = $this->getJson('/api/products/' . $product->id);

        // Verificar que se haya obtenido el producto con éxito y que la respuesta sea 200 (OK)
        $response->assertStatus(200);

        // Verificar que los datos del producto obtenido coinciden con los datos del producto de prueba
        $response->assertJson([
            'id' => $product->id,
            'name' => 'Producto de prueba',
            'price' => 50.00,
            'category_id' => $category->id,
        ]);
    }

    public function test_put_product()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $category = Category::factory()->create();
        $product = Product::factory()->create();

        $data_update = [
            'name' => 'Nuevo nombre',
            'description' => 'Nueva descripción',
            'category_id' => $category->id,
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->putJson('/api/products/' . $product->id, $data_update);

        //$response = $this->putJson('/api/products/'. $product->id, $data_update );
        $response->assertStatus(200);

        $response->assertJson([
            'id' => $product->id,
            'name' => $data_update['name'],
            'description' => $data_update['description'],
        ]);
    }

    public function test_delete_product()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $product = Product::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->deleteJson('/api/products/' . $product->id);

        //$response = $this->deleteJson('/api/products/'. $product->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
