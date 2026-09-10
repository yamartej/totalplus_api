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

class ProductWriteIntegrityPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(
        User $user,
        array $names
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Product Write Integrity'],
            ['description' => 'Phase 4 product write integrity test role']
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
                    ->createToken('phase-4-product-write-integrity')
                    ->plainTextToken,
        ];
    }

    private function makeBatch(
        string $name,
        ?int $companyId
    ): Batch {
        $batch = Batch::create([
            'name' => $name,
            'description' => 'Phase 4 product write integrity test',
            'quantity' => 10,
            'status' => 'created',
            'order_creation_date' => '2026-09-08 12:00:00',
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
            'description' => 'Phase 4 product write integrity test',
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
            'name' => 'Phase 4 Product Write Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 4 Product Write Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $category = Category::factory()->create();

        $ownBatch = $this->makeBatch(
            'Own Direct Write Batch',
            (int) $companyA->id
        );

        $otherBatch = $this->makeBatch(
            'Other Direct Write Batch',
            (int) $companyB->id
        );

        $legacyBatch = $this->makeBatch(
            'Legacy Direct Write Batch',
            null
        );

        $ownProduct = $this->makeProduct(
            'Own Direct Write Product',
            (int) $companyA->id,
            (int) $category->id
        );

        $legacyProduct = $this->makeProduct(
            'Legacy Direct Write Product',
            null,
            (int) $category->id,
            (int) $legacyBatch->id
        );

        return compact(
            'companyA',
            'companyB',
            'userA',
            'category',
            'ownBatch',
            'otherBatch',
            'legacyBatch',
            'ownProduct',
            'legacyProduct'
        );
    }

    private function productPayload(
        array $ctx,
        string $name,
        ?int $batchId
    ): array {
        return [
            'name' => $name,
            'description' => 'Direct product write payload',
            'category_id' => $ctx['category']->id,
            'price' => 6.00,
            'quantity' => 12,
            'batch_id' => $batchId,
        ];
    }

    public function test_product_can_be_created_with_own_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.create']
                )
            )
            ->postJson(
                '/api/products',
                $this->productPayload(
                    $ctx,
                    'Created With Own Batch',
                    (int) $ctx['ownBatch']->id
                )
            );

        $response->assertStatus(200);

        $product = Product::where(
            'name',
            'Created With Own Batch'
        )->first();

        $this->assertNotNull($product);

        $this->assertSame(
            (int) $ctx['companyA']->id,
            (int) $product->company_id
        );

        $this->assertSame(
            (int) $ctx['ownBatch']->id,
            (int) $product->batch_id
        );
    }

    public function test_product_cannot_be_created_with_other_company_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.create']
                )
            )
            ->postJson(
                '/api/products',
                $this->productPayload(
                    $ctx,
                    'Created With Foreign Batch',
                    (int) $ctx['otherBatch']->id
                )
            );

        $response->assertStatus(404);

        $this->assertFalse(
            Product::where(
                'name',
                'Created With Foreign Batch'
            )->exists()
        );
    }

    public function test_product_cannot_be_created_with_legacy_unowned_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.create']
                )
            )
            ->postJson(
                '/api/products',
                $this->productPayload(
                    $ctx,
                    'Created With Legacy Batch',
                    (int) $ctx['legacyBatch']->id
                )
            );

        $response->assertStatus(404);

        $this->assertFalse(
            Product::where(
                'name',
                'Created With Legacy Batch'
            )->exists()
        );
    }

    public function test_owned_product_can_be_updated_to_own_batch(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson(
                '/api/products/' . $ctx['ownProduct']->id,
                $this->productPayload(
                    $ctx,
                    'Owned Product Updated',
                    (int) $ctx['ownBatch']->id
                )
            );

        $response->assertStatus(200);

        $product = $ctx['ownProduct']->fresh();

        $this->assertSame(
            'Owned Product Updated',
            $product->name
        );

        $this->assertSame(
            (int) $ctx['ownBatch']->id,
            (int) $product->batch_id
        );
    }

    public function test_owned_product_cannot_be_updated_to_other_company_batch(): void
    {
        $ctx = $this->context();

        $originalName = $ctx['ownProduct']->name;
        $originalBatchId = $ctx['ownProduct']->batch_id;

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson(
                '/api/products/' . $ctx['ownProduct']->id,
                $this->productPayload(
                    $ctx,
                    'Should Not Be Applied Foreign',
                    (int) $ctx['otherBatch']->id
                )
            );

        $response->assertStatus(404);

        $product = $ctx['ownProduct']->fresh();

        $this->assertSame(
            $originalName,
            $product->name
        );

        $this->assertSame(
            $originalBatchId,
            $product->batch_id
        );
    }

    public function test_owned_product_cannot_be_updated_to_legacy_unowned_batch(): void
    {
        $ctx = $this->context();

        $originalName = $ctx['ownProduct']->name;
        $originalBatchId = $ctx['ownProduct']->batch_id;

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson(
                '/api/products/' . $ctx['ownProduct']->id,
                $this->productPayload(
                    $ctx,
                    'Should Not Be Applied Legacy',
                    (int) $ctx['legacyBatch']->id
                )
            );

        $response->assertStatus(404);

        $product = $ctx['ownProduct']->fresh();

        $this->assertSame(
            $originalName,
            $product->name
        );

        $this->assertSame(
            $originalBatchId,
            $product->batch_id
        );
    }

    public function test_legacy_product_cannot_be_updated_by_company_tenant(): void
    {
        $ctx = $this->context();

        $originalName = $ctx['legacyProduct']->name;
        $originalBatchId = $ctx['legacyProduct']->batch_id;

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson(
                '/api/products/' . $ctx['legacyProduct']->id,
                $this->productPayload(
                    $ctx,
                    'Legacy Product Must Not Change',
                    (int) $ctx['ownBatch']->id
                )
            );

        $response->assertStatus(404);

        $product = $ctx['legacyProduct']->fresh();

        $this->assertSame(
            $originalName,
            $product->name
        );

        $this->assertSame(
            $originalBatchId,
            $product->batch_id
        );
    }

    public function test_owned_product_can_be_deleted(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.delete']
                )
            )
            ->deleteJson(
                '/api/products/' . $ctx['ownProduct']->id
            );

        $response->assertStatus(204);

        $this->assertNull(
            Product::find($ctx['ownProduct']->id)
        );
    }

    public function test_legacy_product_cannot_be_deleted_by_company_tenant(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.delete']
                )
            )
            ->deleteJson(
                '/api/products/' . $ctx['legacyProduct']->id
            );

        $response->assertStatus(404);

        $this->assertNotNull(
            Product::find($ctx['legacyProduct']->id)
        );
    }
}
