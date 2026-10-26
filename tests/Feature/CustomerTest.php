<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Customer identity (client_id) and authenticated tenant CRUD contract. */
class CustomerTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/customers';
    protected string $modelClass = Customer::class;

    protected function fixture(): Model
    {
        return Customer::create([
            'client_id' => 22000001, 'name' => 'QA customer',
            'address' => 'QA street', 'phone' => '123456',
            'company_id' => $this->qaCompany->id,
        ]);
    }

    protected function createPayload(): array
    {
        return [
            'client_id' => 22000002, 'name' => 'Created customer',
            'address' => 'QA street', 'phone' => '123456',
        ];
    }

    protected function updatePayload(): array
    {
        return [
            'client_id' => 22000003, 'name' => 'Updated customer',
            'address' => 'QA updated address', 'phone' => '987654',
        ];
    }

    public function test_show_record(): void
    {
        $record = $this->fixture();

        // This legacy endpoint resolves by client_id and returns a collection.
        $response = $this->getJson(
            $this->endpoint . '/' . $record->client_id
        )->assertOk();

        $this->assertContains(
            (int) $record->id,
            array_map('intval', array_column($response->json(), 'id'))
        );
    }

    public function test_example(): void
    {
        $this->get('/')->assertOk();
    }
}
