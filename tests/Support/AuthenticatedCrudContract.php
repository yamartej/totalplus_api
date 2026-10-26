<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API regression contract used by previously unauthenticated legacy tests.
 * Each concrete test class exercises real authorization, tenancy and CRUD.
 */
abstract class AuthenticatedCrudContract extends TestCase
{
    use RefreshDatabase;
    use AuthenticatedCompany;

    protected string $endpoint;
    protected string $modelClass;

    abstract protected function fixture(): Model;

    abstract protected function createPayload(): array;

    abstract protected function updatePayload(): array;

    protected function expectedCreateStatus(): int
    {
        return 201;
    }

    protected function idFromCreationResponse(array $json): int
    {
        return (int) $json['id'];
    }

    public function test_list_records(): void
    {
        $record = $this->fixture();
        $response = $this->getJson($this->endpoint)->assertOk();
        $this->assertContains(
            (int) $record->id,
            array_map('intval', array_column($response->json(), 'id'))
        );
    }

    public function test_create_record(): void
    {
        $response = $this->postJson(
            $this->endpoint,
            $this->createPayload()
        )->assertStatus($this->expectedCreateStatus());

        $id = $this->idFromCreationResponse($response->json());
        $this->assertGreaterThan(0, $id);
        $this->assertNotNull(($this->modelClass)::find($id));
    }

    public function test_show_record(): void
    {
        $record = $this->fixture();
        $this->getJson($this->endpoint . '/' . $record->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $record->id]);
    }

    public function test_update_record(): void
    {
        $record = $this->fixture();
        $this->putJson(
            $this->endpoint . '/' . $record->id,
            $this->updatePayload()
        )->assertOk();

        $this->assertNotNull(($this->modelClass)::find($record->id));
    }

    public function test_delete_record(): void
    {
        $record = $this->fixture();
        $this->deleteJson($this->endpoint . '/' . $record->id)
            ->assertNoContent();

        $this->assertNull(($this->modelClass)::find($record->id));
    }
}
