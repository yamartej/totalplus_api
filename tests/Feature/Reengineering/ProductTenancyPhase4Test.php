<?php

namespace Tests\Feature\Reengineering;

use App\Models\Category;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Roles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTenancyPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(User $user, array $names): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Product Tenancy'],
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

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function authHeaders(User $user, array $permissions): array
    {
        $this->grantPermissions($user, $permissions);

        return [
            'Authorization' => 'Bearer '
                . $user
                    ->createToken('phase-4-product-tenancy')
                    ->plainTextToken,
        ];
    }

    private function context(): array
    {
        $companyA = Company::create(['name' => 'Phase 4 Company A']);
        $companyB = Company::create(['name' => 'Phase 4 Company B']);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $category = Category::create([
            'name' => 'Phase 4 Category',
            'description' => 'Testing',
        ]);

        $ownProduct = Product::factory()->create([
            'name' => 'Own Product',
            'description' => 'Own',
            'category_id' => $category->id,
            'quantity' => 10,
            'price' => 5,
            'final_cost' => 10,
            'wholesale_final_cost' => 8,
        ]);
        $ownProduct->company_id = $companyA->id;
        $ownProduct->save();

        $otherProduct = Product::factory()->create([
            'name' => 'Other Company Product',
            'description' => 'Other',
            'category_id' => $category->id,
            'quantity' => 10,
            'price' => 5,
            'final_cost' => 10,
            'wholesale_final_cost' => 8,
        ]);
        $otherProduct->company_id = $companyB->id;
        $otherProduct->save();

        $legacyProduct = Product::factory()->create([
            'name' => 'Legacy Shared Product',
            'description' => 'Legacy',
            'category_id' => $category->id,
            'quantity' => 10,
            'price' => 5,
            'final_cost' => 10,
            'wholesale_final_cost' => 8,
        ]);
        $legacyProduct->company_id = null;
        $legacyProduct->save();

        return compact(
            'companyA',
            'companyB',
            'userA',
            'category',
            'ownProduct',
            'otherProduct',
            'legacyProduct'
        );
    }

    public function test_product_index_excludes_other_company_products(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.view']
                )
            )
            ->getJson('/api/products');

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

    public function test_new_product_is_owned_by_authenticated_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.create']
                )
            )
            ->postJson('/api/products', [
                'name' => 'Created Product',
                'description' => 'Created in Phase 4',
                'price' => 12.50,
                'category_id' => $ctx['category']->id,
                'quantity' => 20,
                'batch_id' => null,
            ]);

        $response->assertStatus(200);

        $created = Product::where(
            'name',
            'Created Product'
        )->firstOrFail();

        $this->assertSame(
            (int) $ctx['companyA']->id,
            (int) $created->company_id
        );
    }

    public function test_other_company_product_cannot_be_read_by_id(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.view']
                )
            )
            ->getJson(
                '/api/products/' . $ctx['otherProduct']->id
            );

        $response->assertStatus(404);
    }

    public function test_other_company_final_price_cannot_be_updated(): void
    {
        $ctx = $this->context();

        $beforeRetail = (float) $ctx['otherProduct']->final_cost;
        $beforeWholesale = (float)
            $ctx['otherProduct']->wholesale_final_cost;

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['products.update']
                )
            )
            ->putJson('/api/products/update-final-cost', [
                'id' => $ctx['otherProduct']->id,
                'final_cost' => 999.99,
                'wholesale_final_cost' => 888.88,
            ]);

        $response->assertStatus(404);

        $fresh = $ctx['otherProduct']->fresh();

        $this->assertSame(
            $beforeRetail,
            (float) $fresh->final_cost
        );

        $this->assertSame(
            $beforeWholesale,
            (float) $fresh->wholesale_final_cost
        );
    }
}
