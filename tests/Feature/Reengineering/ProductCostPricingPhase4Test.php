<?php

namespace Tests\Feature\Reengineering;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Cost;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCostPricingPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(
        User $user,
        array $names
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Product Cost Pricing'],
            ['description' => 'Phase 4 costing/pricing test role']
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
                    ->createToken('phase-4-product-cost-pricing')
                    ->plainTextToken,
        ];
    }

    private function makeBatch(
        string $name,
        ?int $companyId,
        float $costAmount
    ): Batch {
        $batch = Batch::create([
            'name' => $name,
            'description' => 'Phase 4 cost/pricing test',
            'quantity' => 100,
            'status' => 'created',
            'order_creation_date' => '2026-09-08 12:00:00',
        ]);

        $batch->company_id = $companyId;
        $batch->save();

        Cost::create([
            'amount' => $costAmount,
            'description' => $name . ' shipping',
            'batch_id' => $batch->id,
        ]);

        return $batch;
    }

    private function makeProduct(
        string $name,
        ?int $companyId,
        int $categoryId,
        int $batchId,
        int $quantity,
        float $price,
        float $finalCost,
        float $wholesaleFinalCost
    ): Product {
        $product = Product::create([
            'name' => $name,
            'description' => 'Phase 4 cost/pricing test',
            'price' => $price,
            'category_id' => $categoryId,
            'quantity' => $quantity,
            'batch_id' => $batchId,
            'final_cost' => $finalCost,
            'wholesale_final_cost' => $wholesaleFinalCost,
        ]);

        $product->company_id = $companyId;
        $product->save();

        return $product;
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 4 Cost Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 4 Cost Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $category = Category::factory()->create();

        $ownBatch = $this->makeBatch(
            'Own Cost Batch',
            (int) $companyA->id,
            100.00
        );

        $otherBatch = $this->makeBatch(
            'Other Cost Batch',
            (int) $companyB->id,
            200.00
        );

        $legacyBatch = $this->makeBatch(
            'Legacy Cost Batch',
            null,
            50.00
        );

        $ownProduct = $this->makeProduct(
            'Own Cost Product',
            (int) $companyA->id,
            (int) $category->id,
            (int) $ownBatch->id,
            10,
            2.00,
            25.00,
            20.00
        );

        /*
         * Incompatible legacy association. It must not dilute the denominator
         * of an owned batch and must not be presented as a valid cost row.
         */
        $legacyOnOwnBatch = $this->makeProduct(
            'Legacy Product On Own Batch',
            null,
            (int) $category->id,
            (int) $ownBatch->id,
            90,
            3.00,
            30.00,
            24.00
        );

        $otherProduct = $this->makeProduct(
            'Other Cost Product',
            (int) $companyB->id,
            (int) $category->id,
            (int) $otherBatch->id,
            10,
            4.00,
            40.00,
            32.00
        );

        /*
         * Malformed cross-company association: the product belongs to A but
         * points to company B's batch. Cost reads must not expose foreign
         * batch costs through this product.
         */
        $ownProductOnOtherBatch = $this->makeProduct(
            'Own Product On Other Batch',
            (int) $companyA->id,
            (int) $category->id,
            (int) $otherBatch->id,
            10,
            5.00,
            50.00,
            40.00
        );

        $legacyProduct = $this->makeProduct(
            'Legacy Cost Product',
            null,
            (int) $category->id,
            (int) $legacyBatch->id,
            5,
            1.00,
            15.00,
            12.00
        );

        return compact(
            'companyA',
            'companyB',
            'userA',
            'ownBatch',
            'otherBatch',
            'legacyBatch',
            'ownProduct',
            'legacyOnOwnBatch',
            'otherProduct',
            'ownProductOnOtherBatch',
            'legacyProduct'
        );
    }

    private function costRows(array $ctx): array
    {
        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.view']
                )
            )
            ->getJson('/api/products/with-costs');

        $response->assertStatus(200);

        return $response->json();
    }

    private function rowById(
        array $rows,
        int $id
    ): ?array {
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }

    public function test_owned_batch_cost_uses_only_compatible_owned_quantity(): void
    {
        $ctx = $this->context();

        $rows = $this->costRows($ctx);

        $row = $this->rowById(
            $rows,
            (int) $ctx['ownProduct']->id
        );

        $this->assertNotNull($row);

        /*
         * 100 batch costs / 10 compatible owned units = 10 unit cost.
         * The incompatible NULL-company product on this owned batch has
         * quantity 90 and must not dilute the denominator to 100.
         */
        $this->assertSame(
            10.0,
            (float) $row['unit_cost']
        );

        $this->assertSame(
            12.0,
            (float) $row['price_shipping']
        );

        /*
         * Selling prices are persisted business values, not derived from
         * unit_cost or price_shipping.
         */
        $this->assertSame(
            25.0,
            (float) $row['final_cost']
        );

        $this->assertSame(
            20.0,
            (float) $row['wholesale_final_cost']
        );

        /*
         * Cost reads must not reinterpret or mutate the legacy purchase/batch
         * quantity field.
         */
        $this->assertSame(
            10,
            (int) $ctx['ownProduct']->fresh()->quantity
        );
    }

    public function test_incompatible_legacy_product_on_owned_batch_is_excluded(): void
    {
        $ctx = $this->context();

        $rows = $this->costRows($ctx);

        $this->assertNull(
            $this->rowById(
                $rows,
                (int) $ctx['legacyOnOwnBatch']->id
            )
        );
    }

    public function test_explicit_other_company_product_is_excluded_from_cost_rows(): void
    {
        $ctx = $this->context();

        $rows = $this->costRows($ctx);

        $this->assertNull(
            $this->rowById(
                $rows,
                (int) $ctx['otherProduct']->id
            )
        );
    }

    public function test_owned_product_linked_to_foreign_batch_is_excluded(): void
    {
        $ctx = $this->context();

        $rows = $this->costRows($ctx);

        $this->assertNull(
            $this->rowById(
                $rows,
                (int) $ctx['ownProductOnOtherBatch']->id
            )
        );
    }

    public function test_legacy_product_on_legacy_batch_remains_readable(): void
    {
        $ctx = $this->context();

        $rows = $this->costRows($ctx);

        $row = $this->rowById(
            $rows,
            (int) $ctx['legacyProduct']->id
        );

        $this->assertNotNull($row);

        $this->assertSame(
            10.0,
            (float) $row['unit_cost']
        );

        $this->assertSame(
            11.0,
            (float) $row['price_shipping']
        );

        $this->assertSame(
            15.0,
            (float) $row['final_cost']
        );

        $this->assertSame(
            12.0,
            (float) $row['wholesale_final_cost']
        );
    }

    public function test_owned_product_final_prices_can_be_updated(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-final-cost', [
                'id' => $ctx['ownProduct']->id,
                'final_cost' => 27.50,
                'wholesale_final_cost' => 21.25,
            ]);

        $response->assertStatus(200);

        $product = $ctx['ownProduct']->fresh();

        $this->assertSame(
            27.5,
            (float) $product->final_cost
        );

        $this->assertSame(
            21.25,
            (float) $product->wholesale_final_cost
        );
    }

    public function test_legacy_product_final_prices_are_not_writable_by_tenant(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-final-cost', [
                'id' => $ctx['legacyProduct']->id,
                'final_cost' => 999.00,
                'wholesale_final_cost' => 888.00,
            ]);

        $response->assertStatus(404);

        $product = $ctx['legacyProduct']->fresh();

        $this->assertSame(
            15.0,
            (float) $product->final_cost
        );

        $this->assertSame(
            12.0,
            (float) $product->wholesale_final_cost
        );
    }
}
