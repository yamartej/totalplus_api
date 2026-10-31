<?php

namespace Tests\Feature;

use App\Models\Roles;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression of the current authenticated API contract, not the pre-tenancy routes. */
class RolesTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/roles';
    protected string $modelClass = Roles::class;

    protected function expectedCreateStatus(): int
    {
        return 201; // JsonResource correctly reports a newly created model.
    }

    protected function idFromCreationResponse(array $json): int
    {
        return (int) $json['data']['id'];
    }

    protected function fixture(): Model
    {
        return Roles::create(['name' => 'Listed Role '.uniqid(), 'description' => 'QA']);
    }
    protected function createPayload(): array
    {
        return ['name' => 'Created Role '.uniqid(), 'description' => 'QA'];
    }
    protected function updatePayload(): array
    {
        return ['name' => 'Updated Role '.uniqid(), 'description' => 'QA'];
    }
}
