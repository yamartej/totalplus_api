<?php

namespace Tests\Feature;

use App\Models\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Http\Response;

class RolesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_all_roles()
    {
        Roles::factory()->count(3)->create();

        $response = $this->get('/api/roles');

        $response->assertStatus(200);

        $response->assertJsonStructure();
    }

    public function test_store_rol()
    {
        $rol = [
            'name' => $this->faker->word, 
            'description' => $this->faker->word, 
        ];
       
        $response = $this->post('/api/roles', $rol);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonStructure();
    }

    public function test_show_rol()
    {
        $rol = Roles::factory()->create([
            'name' => $this->faker->word, 
            'description' => $this->faker->word,
        ]);

        $response = $this->getJson('/api/roles/'. $rol->id);

        $response->assertStatus(200);

        $response->assertJsonStructure();
 
    }

    public function test_put_rol()
    {
        $rol = Roles::factory()->create();

        $rol_data = ['name' => 'Otro Rol', 'description' => 'Otra descripcion'];

        $response = $this->putJson('/api/roles/'. $rol->id, $rol_data );
        
        $response->assertStatus(200);
 
        $response->assertJsonStructure();
        
    }

    public function test_delete_rol()
    {
        $rol = Roles::factory()->create();
        
        $response = $this->deleteJson('/api/roles/'. $rol->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('roles', [
            'id' => $rol->id,
            'name' => $rol->name,
            'description'=> $rol->description,
            'created_at'=> $rol->created_at,
            'updated_at' => $rol->updated_at,
        ]);
    }

}
