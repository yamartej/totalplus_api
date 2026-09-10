<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
use App\Models\Company;
use App\Models\Cost;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchCostTenancyPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(
        User $user,
        array $names
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Batch Cost Tenancy'],
            ['description' => 'Phase 4 test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 4 test permission']
                )->id;
            })
            ->all();

        $role->permissions()->syncWithoutDetaching(
            $permissionIds
        );

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);
    }

    private function authHeaders(
        User $user,
        array $permissions
    ): array {
        $this->grantPermissions(
            $user,
            $permissions
        );

        return [
            'Authorization' => 'Bearer '
                . $user
                    ->createToken('phase-4-batch-cost-tenancy')
                    ->plainTextToken,
        ];
    }

    private function makeBatch(
        string $name,
        ?int $companyId
    ): Batch {
        $batch = Batch::create([
            'name' => $name,
            'description' => 'Phase 4 tenancy test',
            'quantity' => 10,
            'status' => 'created',
            'order_creation_date' => '2026-09-06 12:00:00',
        ]);

        $batch->company_id = $companyId;
        $batch->save();

        return $batch;
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 4 Batch Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 4 Batch Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $ownBatch = $this->makeBatch(
            'Own Batch',
            (int) $companyA->id
        );

        $otherBatch = $this->makeBatch(
            'Other Batch',
            (int) $companyB->id
        );

        $legacyBatch = $this->makeBatch(
            'Legacy Batch',
            null
        );

        $ownCost = Cost::create([
            'amount' => 10.50,
            'description' => 'Own Cost',
            'batch_id' => $ownBatch->id,
        ]);

        $otherCost = Cost::create([
            'amount' => 20.50,
            'description' => 'Other Cost',
            'batch_id' => $otherBatch->id,
        ]);

        $legacyCost = Cost::create([
            'amount' => 30.50,
            'description' => 'Legacy Cost',
            'batch_id' => $legacyBatch->id,
        ]);

        return compact(
            'companyA',
            'companyB',
            'userA',
            'ownBatch',
            'otherBatch',
            'legacyBatch',
            'ownCost',
            'otherCost',
            'legacyCost'
        );
    }

    public function test_batch_index_excludes_other_company_batches(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['batches.view']
                )
            )
            ->getJson('/api/batches');

        $response->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue(
            $ids->contains((int) $ctx['ownBatch']->id)
        );

        $this->assertTrue(
            $ids->contains((int) $ctx['legacyBatch']->id)
        );

        $this->assertFalse(
            $ids->contains((int) $ctx['otherBatch']->id)
        );
    }

    public function test_new_batch_is_owned_by_authenticated_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['batches.create']
                )
            )
            ->postJson('/api/batches', [
                'name' => 'Created Batch',
                'description' => 'Created in Phase 4',
                'quantity' => 12,
                'status' => 'created',
                'order_creation_date' => '2026-09-06',
            ]);

        $response->assertStatus(201);

        $batch = Batch::where(
            'name',
            'Created Batch'
        )->firstOrFail();

        $this->assertSame(
            (int) $ctx['companyA']->id,
            (int) $batch->company_id
        );
    }

    public function test_other_company_batch_cannot_be_updated(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['batches.update']
                )
            )
            ->putJson(
                '/api/batches/' . $ctx['otherBatch']->id,
                [
                    'name' => 'Hacked Batch',
                    'description' => 'Cross company',
                    'quantity' => 99,
                    'status' => 'received',
                    'order_creation_date' => '2026-09-06',
                ]
            );

        $response->assertStatus(404);

        $this->assertSame(
            'Other Batch',
            $ctx['otherBatch']->fresh()->name
        );
    }

    public function test_other_company_batch_cannot_be_deleted(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['batches.delete']
                )
            )
            ->deleteJson(
                '/api/batches/' . $ctx['otherBatch']->id
            );

        $response->assertStatus(404);

        $this->assertDatabaseHas('batches', [
            'id' => $ctx['otherBatch']->id,
        ]);
    }

    public function test_cost_index_excludes_other_company_costs(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['costs.view']
                )
            )
            ->getJson('/api/costs');

        $response->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue(
            $ids->contains((int) $ctx['ownCost']->id)
        );

        $this->assertTrue(
            $ids->contains((int) $ctx['legacyCost']->id)
        );

        $this->assertFalse(
            $ids->contains((int) $ctx['otherCost']->id)
        );
    }

    public function test_cost_cannot_be_created_for_other_company_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['costs.create']
                )
            )
            ->postJson('/api/costs', [
                'amount' => 50.00,
                'description' => 'Cross company cost',
                'batch_id' => $ctx['otherBatch']->id,
            ]);

        $response->assertStatus(404);

        $this->assertDatabaseMissing('costs', [
            'description' => 'Cross company cost',
        ]);
    }

    public function test_other_company_cost_cannot_be_updated(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['costs.update']
                )
            )
            ->putJson(
                '/api/costs/' . $ctx['otherCost']->id,
                [
                    'amount' => 999.99,
                    'description' => 'Hacked Cost',
                    'batch_id' => $ctx['otherBatch']->id,
                ]
            );

        $response->assertStatus(404);

        $this->assertSame(
            'Other Cost',
            $ctx['otherCost']->fresh()->description
        );
    }

    public function test_other_company_cost_cannot_be_deleted(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['costs.delete']
                )
            )
            ->deleteJson(
                '/api/costs/' . $ctx['otherCost']->id
            );

        $response->assertStatus(404);

        $this->assertDatabaseHas('costs', [
            'id' => $ctx['otherCost']->id,
        ]);
    }
}
