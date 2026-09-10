<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBatchTenancyPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(
        User $user,
        array $names
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Product Batch Tenancy'],
            ['description' => 'Phase 4 product/batch tenancy test role']
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
                    ->createToken('phase-4-product-batch-tenancy')
                    ->plainTextToken,
        ];
    }

    private function makeBatch(
        string $name,
        ?int $companyId,
        string $status = 'created'
    ): Batch {
        $batch = Batch::create([
            'name' => $name,
            'description' => 'Phase 4 product/batch tenancy test',
            'quantity' => 10,
            'status' => $status,
            'order_creation_date' => '2026-09-06 12:00:00',
        ]);

        $batch->company_id = $companyId;
        $batch->save();

        return $batch;
    }

    private function makeProduct(
        string $name,
        ?int $companyId,
        int $categoryId,
        ?int $batchId = null
    ): Product {
        $product = Product::create([
            'name' => $name,
            'description' => 'Phase 4 product/batch tenancy test',
            'price' => 5.00,
            'category_id' => $categoryId,
            'quantity' => 10,
            'batch_id' => $batchId,
            'final_cost' => 10.00,
            'wholesale_final_cost' => 8.00,
        ]);

        $product->company_id = $companyId;
        $product->save();

        return $product;
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 4 Product Batch Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 4 Product Batch Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $category = Category::factory()->create();

        $ownBatch = $this->makeBatch(
            'Own Product Batch',
            (int) $companyA->id
        );

        $otherBatch = $this->makeBatch(
            'Other Product Batch',
            (int) $companyB->id
        );

        $legacyBatch = $this->makeBatch(
            'Legacy Product Batch',
            null
        );

        $ownProduct = $this->makeProduct(
            'Own Product',
            (int) $companyA->id,
            (int) $category->id
        );

        $secondOwnProduct = $this->makeProduct(
            'Second Own Product',
            (int) $companyA->id,
            (int) $category->id
        );

        $otherProduct = $this->makeProduct(
            'Other Product',
            (int) $companyB->id,
            (int) $category->id
        );

        $legacyProduct = $this->makeProduct(
            'Legacy Product',
            null,
            (int) $category->id,
            (int) $legacyBatch->id
        );

        return compact(
            'companyA',
            'companyB',
            'userA',
            'ownBatch',
            'otherBatch',
            'legacyBatch',
            'ownProduct',
            'secondOwnProduct',
            'otherProduct',
            'legacyProduct'
        );
    }

    public function test_own_products_can_be_assigned_to_own_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-batch', [
                'product_ids' => [
                    $ctx['ownProduct']->id,
                    $ctx['secondOwnProduct']->id,
                ],
                'batch_id' => $ctx['ownBatch']->id,
            ]);

        $response->assertStatus(200);

        $this->assertSame(
            (int) $ctx['ownBatch']->id,
            (int) $ctx['ownProduct']->fresh()->batch_id
        );

        $this->assertSame(
            (int) $ctx['ownBatch']->id,
            (int) $ctx['secondOwnProduct']->fresh()->batch_id
        );
    }

    public function test_own_product_cannot_be_assigned_to_other_company_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-batch', [
                'product_ids' => [
                    $ctx['ownProduct']->id,
                ],
                'batch_id' => $ctx['otherBatch']->id,
            ]);

        $response->assertStatus(404);

        $this->assertNull(
            $ctx['ownProduct']->fresh()->batch_id
        );
    }

    public function test_other_company_product_cannot_be_assigned_to_own_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-batch', [
                'product_ids' => [
                    $ctx['otherProduct']->id,
                ],
                'batch_id' => $ctx['ownBatch']->id,
            ]);

        $response->assertStatus(404);

        $this->assertNull(
            $ctx['otherProduct']->fresh()->batch_id
        );
    }

    public function test_mixed_company_assignment_is_rejected_atomically(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-batch', [
                'product_ids' => [
                    $ctx['ownProduct']->id,
                    $ctx['otherProduct']->id,
                ],
                'batch_id' => $ctx['ownBatch']->id,
            ]);

        $response->assertStatus(404);

        $this->assertNull(
            $ctx['ownProduct']->fresh()->batch_id
        );

        $this->assertNull(
            $ctx['otherProduct']->fresh()->batch_id
        );
    }

    public function test_legacy_unowned_batch_cannot_receive_new_assignment(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-batch', [
                'product_ids' => [
                    $ctx['ownProduct']->id,
                ],
                'batch_id' => $ctx['legacyBatch']->id,
            ]);

        $response->assertStatus(404);

        $this->assertNull(
            $ctx['ownProduct']->fresh()->batch_id
        );
    }

    public function test_product_batch_status_list_excludes_other_company_products(): void
    {
        $ctx = $this->context();

        $ctx['ownProduct']->batch_id = $ctx['ownBatch']->id;
        $ctx['ownProduct']->save();

        $ctx['otherProduct']->batch_id = $ctx['otherBatch']->id;
        $ctx['otherProduct']->save();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.view']
                )
            )
            ->getJson('/api/products/with-batch-and-status');

        $response->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue(
            $ids->contains((int) $ctx['ownProduct']->id)
        );

        $this->assertTrue(
            $ids->contains((int) $ctx['legacyProduct']->id)
        );

        $this->assertFalse(
            $ids->contains((int) $ctx['otherProduct']->id)
        );
    }

    public function test_product_batch_status_list_requires_compatible_batch_tenancy(): void
    {
        $ctx = $this->context();

        /*
         * Simulate malformed/legacy data: an own-company product points to a
         * foreign-company batch. Read APIs must not expose it as a valid
         * tenant product/batch association.
         */
        $ctx['ownProduct']->batch_id = $ctx['otherBatch']->id;
        $ctx['ownProduct']->save();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.view']
                )
            )
            ->getJson('/api/products/with-batch-and-status');

        $response->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertFalse(
            $ids->contains((int) $ctx['ownProduct']->id)
        );
    }
}
