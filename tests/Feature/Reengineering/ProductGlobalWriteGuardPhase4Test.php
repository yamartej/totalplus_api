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

class ProductGlobalWriteGuardPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(User $user, array $names): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 4 Global Product Write Guard'],
            ['description' => 'Phase 4 global product write guard test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 4 global write guard test permission']
                )->id;
            })
            ->all();

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function authHeaders(User $user, array $permissions): array
    {
        $this->grantPermissions(
            $user,
            array_values(array_unique(array_merge(
                ['tenant.cross_company'],
                $permissions
            )))
        );

        return [
            'Authorization' => 'Bearer '
                . $user
                    ->createToken('phase-4-global-product-write-guard')
                    ->plainTextToken,
        ];
    }

    private function makeProduct(
        string $name,
        int $companyId,
        int $categoryId
    ): Product {
        $product = Product::create([
            'name' => $name,
            'description' => 'Phase 4 global product write guard fixture',
            'price' => 5.00,
            'category_id' => $categoryId,
            'quantity' => 10,
            'batch_id' => null,
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
            'name' => 'Phase 4 Global Guard Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 4 Global Guard Company B',
        ]);

        $globalUser = User::factory()->create([
            'company_id' => null,
        ]);

        $category = Category::factory()->create();

        $productA = $this->makeProduct(
            'Global Guard Product A',
            (int) $companyA->id,
            (int) $category->id
        );

        $productB = $this->makeProduct(
            'Global Guard Product B',
            (int) $companyB->id,
            (int) $category->id
        );

        return compact(
            'companyA',
            'companyB',
            'globalUser',
            'category',
            'productA',
            'productB'
        );
    }

    private function updatePayload(
        array $ctx,
        string $name,
        ?int $companyId = null
    ): array {
        $payload = [
            'name' => $name,
            'description' => 'Global write guard update payload',
            'category_id' => $ctx['category']->id,
            'price' => 6.00,
            'quantity' => 11,
            'batch_id' => null,
        ];

        if ($companyId !== null) {
            $payload['company_id'] = $companyId;
        }

        return $payload;
    }

    public function test_global_user_cannot_update_product_without_company_context(): void
    {
        $ctx = $this->context();
        $originalName = $ctx['productA']->name;

        $response = $this
            ->withHeaders($this->authHeaders(
                $ctx['globalUser'],
                ['products.update']
            ))
            ->putJson(
                '/api/products/' . $ctx['productA']->id,
                $this->updatePayload(
                    $ctx,
                    'Must Not Update Without Tenant'
                )
            );

        $response->assertStatus(422);
        $this->assertSame(
            $originalName,
            $ctx['productA']->fresh()->name
        );
    }

    public function test_global_user_cannot_delete_product_without_company_context(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders($this->authHeaders(
                $ctx['globalUser'],
                ['products.delete']
            ))
            ->deleteJson(
                '/api/products/' . $ctx['productA']->id
            );

        $response->assertStatus(422);
        $this->assertNotNull(
            Product::find($ctx['productA']->id)
        );
    }

    public function test_global_user_cannot_update_final_price_without_company_context(): void
    {
        $ctx = $this->context();

        $originalFinal = (float) $ctx['productA']->final_cost;
        $originalWholesale = (float) $ctx['productA']->wholesale_final_cost;

        $response = $this
            ->withHeaders($this->authHeaders(
                $ctx['globalUser'],
                ['products.update']
            ))
            ->putJson(
                '/api/products/update-final-cost',
                [
                    'id' => $ctx['productA']->id,
                    'final_cost' => 99.00,
                    'wholesale_final_cost' => 77.00,
                ]
            );

        $response->assertStatus(422);

        $product = $ctx['productA']->fresh();

        $this->assertSame(
            $originalFinal,
            (float) $product->final_cost
        );
        $this->assertSame(
            $originalWholesale,
            (float) $product->wholesale_final_cost
        );
    }

    public function test_global_user_can_update_product_with_explicit_company_context(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders($this->authHeaders(
                $ctx['globalUser'],
                ['products.update']
            ))
            ->putJson(
                '/api/products/' . $ctx['productA']->id,
                $this->updatePayload(
                    $ctx,
                    'Explicit Tenant Update',
                    (int) $ctx['companyA']->id
                )
            );

        $response->assertStatus(200);
        $this->assertSame(
            'Explicit Tenant Update',
            $ctx['productA']->fresh()->name
        );
    }

    public function test_global_user_cannot_update_other_company_product_when_company_is_selected(): void
    {
        $ctx = $this->context();
        $originalName = $ctx['productB']->name;

        $response = $this
            ->withHeaders($this->authHeaders(
                $ctx['globalUser'],
                ['products.update']
            ))
            ->putJson(
                '/api/products/' . $ctx['productB']->id,
                $this->updatePayload(
                    $ctx,
                    'Wrong Tenant Must Not Update',
                    (int) $ctx['companyA']->id
                )
            );

        $response->assertStatus(404);
        $this->assertSame(
            $originalName,
            $ctx['productB']->fresh()->name
        );
    }
}
