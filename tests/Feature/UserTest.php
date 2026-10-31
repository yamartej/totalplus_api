<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** User CRUD uses an authenticated administrator of the same company. */
class UserTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/users';
    protected string $modelClass = User::class;

    protected function fixture(): Model
    {
        return User::factory()->create(['company_id' => $this->qaCompany->id]);
    }

    protected function createPayload(): array
    {
        return [
            'name' => 'Created User',
            'email' => 'created-'.uniqid().'@example.test',
            'password' => 'Passw0rd123',
            'rol' => [$this->qaAdmin->roles()->firstOrFail()->id],
        ];
    }

    protected function updatePayload(): array
    {
        return ['name' => 'Updated User'];
    }
}
