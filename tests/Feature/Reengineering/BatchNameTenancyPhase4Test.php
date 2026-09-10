<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchNameTenancyPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(
        User $user,
        array $names
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Batch Name Tenancy'],
            ['description' => 'Phase 4 batch name tenancy test role']
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
                    ->createToken('phase-4-batch-name-tenancy')
                    ->plainTextToken,
        ];
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 4 Batch Name Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 4 Batch Name Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $userB = User::factory()->create([
            'company_id' => $companyB->id,
        ]);

        return compact(
            'companyA',
            'companyB',
            'userA',
            'userB'
        );
    }

    private function payload(
        string $name
    ): array {
        return [
            'name' => $name,
            'description' => 'Phase 4 batch name tenancy contract',
            'quantity' => 10,
            'status' => 'created',
            'order_creation_date' => '2026-09-08 12:00:00',
        ];
    }

    private function createOwnedBatch(
        int $companyId,
        string $name
    ): Batch {
        $batch = Batch::create([
            'name' => $name,
            'description' => 'Phase 4 batch name tenancy fixture',
            'quantity' => 10,
            'status' => 'created',
            'order_creation_date' => '2026-09-08 12:00:00',
        ]);

        $batch->company_id = $companyId;
        $batch->save();

        return $batch;
    }

    public function test_two_companies_can_create_batches_with_same_name(): void
    {
        $ctx = $this->context();

        /*
         * Seed the other tenant directly so this contract exercises one HTTP
         * authentication context per test. The invariant under test is that
         * company B may create a name already owned by company A.
         */
        $this->createOwnedBatch(
            (int) $ctx['companyA']->id,
            'SHARED-TENANT-BATCH'
        );

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userB'],
                    ['batches.create']
                )
            )
            ->postJson(
                '/api/batches',
                $this->payload('SHARED-TENANT-BATCH')
            );

        $response->assertStatus(201);

        $this->assertSame(
            2,
            Batch::where('name', 'SHARED-TENANT-BATCH')->count()
        );

        $this->assertSame(
            1,
            Batch::where(
                'company_id',
                $ctx['companyB']->id
            )
                ->where(
                    'name',
                    'SHARED-TENANT-BATCH'
                )
                ->count()
        );
    }

    public function test_same_company_cannot_create_duplicate_batch_name(): void
    {
        $ctx = $this->context();

        $this->createOwnedBatch(
            (int) $ctx['companyA']->id,
            'DUPLICATE-IN-SAME-COMPANY'
        );

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['batches.create']
                )
            )
            ->postJson(
                '/api/batches',
                $this->payload('DUPLICATE-IN-SAME-COMPANY')
            );

        $response->assertStatus(400);

        $this->assertSame(
            1,
            Batch::where(
                'company_id',
                $ctx['companyA']->id
            )
                ->where(
                    'name',
                    'DUPLICATE-IN-SAME-COMPANY'
                )
                ->count()
        );
    }

    public function test_batch_can_be_renamed_to_name_used_only_by_other_company(): void
    {
        $ctx = $this->context();

        $batchA = $this->createOwnedBatch(
            (int) $ctx['companyA']->id,
            'COMPANY-A-ORIGINAL'
        );

        $this->createOwnedBatch(
            (int) $ctx['companyB']->id,
            'CROSS-COMPANY-REUSABLE-NAME'
        );

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['batches.update']
                )
            )
            ->putJson(
                '/api/batches/' . $batchA->id,
                $this->payload('CROSS-COMPANY-REUSABLE-NAME')
            );

        $response->assertStatus(200);

        $this->assertSame(
            'CROSS-COMPANY-REUSABLE-NAME',
            $batchA->fresh()->name
        );
    }

    public function test_batch_cannot_be_renamed_to_name_used_by_same_company(): void
    {
        $ctx = $this->context();

        $batchA1 = $this->createOwnedBatch(
            (int) $ctx['companyA']->id,
            'SAME-COMPANY-NAME-ONE'
        );

        $this->createOwnedBatch(
            (int) $ctx['companyA']->id,
            'SAME-COMPANY-NAME-TWO'
        );

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['batches.update']
                )
            )
            ->putJson(
                '/api/batches/' . $batchA1->id,
                $this->payload('SAME-COMPANY-NAME-TWO')
            );

        $response->assertStatus(400);

        $this->assertSame(
            'SAME-COMPANY-NAME-ONE',
            $batchA1->fresh()->name
        );
    }
}
