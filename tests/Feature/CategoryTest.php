<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\Category;
use Illuminate\Http\Response;

class CategoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_all_categories()
    {
        Category::factory()->count(3)->create();

        $response = $this->get('/api/categories');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'description',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function  test_store_category()
    {
        // Crear una categoria de prueba en la base de datos usando el factory
        $category = [
            'name' => $this->faker->word, 
            'description' => $this->faker->word, 
        ];

        $response = $this->post('/api/categories', $category);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('categories', $category);
    }

    public function test_show_category()
    {
        $category = Category::factory()->create([
            'name' => $this->faker->word, 
            'description' => $this->faker->word,
        ]);

        $response = $this->getJson('/api/categories/'. $category->id);

        $response->assertStatus(200);
 
         // Verificar que los datos del cliente obtenido coinciden con los datos del cliente de prueba
         $response->assertJson([
             'id' => $category->id,
             'name' => $category->name,
             'description' => $category->description,
         ]);
    }

    public function test_put_category()
    {
        $category = Category::factory()->create([
            'name' => $this->faker->word, 
            'description' => $this->faker->word, 
        ]);

        $categoryData = ['name' => 'Otro Nombre', 'description' => 'Otra descripcion'];

        $response = $this->putJson('/api/categories/'. $category->id, $categoryData );
        $response->assertStatus(200);
 
         $response->assertJson([
            'id' => $category->id,
            'name' => $categoryData['name'],
            'description' => $categoryData['description'],
         ]);

    }

    public function test_delete_category()
    {
        $category = Category::factory()->create();
        
        $response = $this->deleteJson('/api/categories/'. $category->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

}
