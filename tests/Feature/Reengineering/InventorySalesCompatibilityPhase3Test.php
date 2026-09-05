<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Roles;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryCompatibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySalesCompatibilityPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function context(
        int $legacyQuantity = 10,
        int $inventoryQuantity = 10
    ): array {
        $company = Company::create([
            'name' => 'Phase 3F2 Company',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Phase 3F2 Warehouse',
            'description' => 'Transactional compatibility warehouse',
            'address' => 'Testing',
            'company_id' => $company->id,
        ]);

        $seller = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 33000001,
            'name' => 'Phase 3F2 Customer',
            'address' => 'Testing',
            'phone' => '0000000000',
        ]);

        $pop = PointOfSale::create([
            'identifier' => 'POS-PHASE3F2-' . uniqid(),
            'ubication' => 'Testing',
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Phase 3F2 Product',
            'quantity' => $legacyQuantity,
            'final_cost' => 100,
            'wholesale_final_cost' => 90,
        ]);

        $product->company_id = $company->id;
        $product->save();

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $inventoryQuantity,
        ]);

        return compact(
            'company',
            'warehouse',
            'seller',
            'customer',
            'pop',
            'product',
            'inventory'
        );
    }

    private function grantPermissions(
        User $user,
        array $names
    ): void {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 3F2 Compatibility'],
            ['description' => 'Phase 3F2 test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 3F2 permission']
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
                    ->createToken('phase-3f2-test')
                    ->plainTextToken,
        ];
    }

    private function salePayload(
        array $ctx,
        int $quantity
    ): array {
        return [
            'client_id' => $ctx['customer']->id,
            'seller_id' => $ctx['seller']->id,
            'pop_id' => $ctx['pop']->id,
            'total' => 1,
            'type_of_sale' => 'normal',
            'carts' => [
                [
                    'productId' => $ctx['product']->id,
                    'warehouse_id' =>
                        $ctx['warehouse']->id,
                    'quantity' => $quantity,
                ],
            ],
        ];
    }

    private function compatibility(): InventoryCompatibilityService
    {
        return app(
            InventoryCompatibilityService::class
        );
    }

    public function test_sold_stock_does_not_reappear_as_unallocated(): void
    {
        $ctx = $this->context(
            legacyQuantity: 10,
            inventoryQuantity: 10
        );

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    [
                        'sales.create',
                        'products.view',
                    ]
                )
            )
            ->postJson(
                '/api/sales',
                $this->salePayload($ctx, 2)
            );

        $response->assertStatus(201);

        $this->assertSame(
            8,
            (int) $ctx['inventory']->fresh()->quantity
        );

        $service = $this->compatibility();

        $this->assertSame(
            2,
            $service->salesDepletionQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $this->assertSame(
            0,
            $service->unallocatedQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $available = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    ['products.view']
                )
            )
            ->getJson('/api/products/available');

        $available->assertStatus(200);

        $ids = collect($available->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertFalse(
            $ids->contains(
                (int) $ctx['product']->id
            )
        );

        $this->assertSame(
            10,
            (int) $ctx['product']->fresh()->quantity
        );
    }

    public function test_sale_preserves_only_preexisting_unallocated_quantity(): void
    {
        $ctx = $this->context(
            legacyQuantity: 10,
            inventoryQuantity: 8
        );

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    [
                        'sales.create',
                        'products.view',
                    ]
                )
            )
            ->postJson(
                '/api/sales',
                $this->salePayload($ctx, 2)
            );

        $response->assertStatus(201);

        $service = $this->compatibility();

        $this->assertSame(
            2,
            $service->unallocatedQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $available = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['seller'],
                    ['products.view']
                )
            )
            ->getJson('/api/products/available');

        $available
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $ctx['product']->id,
                'quantity' => 2,
                'legacy_quantity' => 10,
                'inventory_total_quantity' => 6,
                'unallocated_quantity' => 2,
            ]);
    }

    public function test_sale_void_cancels_depletion_without_creating_unallocated_stock(): void
    {
        $ctx = $this->context();

        $headers = $this->authHeaders(
            $ctx['seller'],
            [
                'sales.create',
                'sales.delete',
            ]
        );

        $create = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/sales',
                $this->salePayload($ctx, 2)
            );

        $create->assertStatus(201);

        $sale = Sale::latest('id')
            ->firstOrFail();

        $service = $this->compatibility();

        $this->assertSame(
            2,
            $service->salesDepletionQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $void = $this
            ->withHeaders($headers)
            ->deleteJson(
                '/api/sales/' . $sale->id
            );

        $void->assertStatus(204);

        $this->assertSame(
            0,
            $service->salesDepletionQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $this->assertSame(
            0,
            $service->unallocatedQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );
    }

    public function test_unassign_after_sale_returns_only_unsold_stock_to_unallocated(): void
    {
        $ctx = $this->context();

        $headers = $this->authHeaders(
            $ctx['seller'],
            [
                'sales.create',
                'inventory.update',
                'products.view',
            ]
        );

        $create = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/sales',
                $this->salePayload($ctx, 2)
            );

        $create->assertStatus(201);

        $unassign = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/inventory/removeAssignedInventory',
                [
                    'product_ids' => [
                        [
                            'id' => $ctx['product']->id,
                            'warehouse_id' =>
                                $ctx['warehouse']->id,
                        ],
                    ],
                ]
            );

        $unassign->assertStatus(204);

        $service = $this->compatibility();

        $this->assertSame(
            8,
            $service->unallocatedQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $available = $this
            ->withHeaders($headers)
            ->getJson('/api/products/available');

        $available
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $ctx['product']->id,
                'quantity' => 8,
                'legacy_quantity' => 10,
                'inventory_total_quantity' => 0,
                'unallocated_quantity' => 8,
            ]);
    }

    public function test_detail_updates_and_removal_keep_depletion_in_sync(): void
    {
        $ctx = $this->context();

        $headers = $this->authHeaders(
            $ctx['seller'],
            [
                'sales.create',
                'sales.update',
                'sales.delete',
            ]
        );

        $create = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/sales',
                $this->salePayload($ctx, 2)
            );

        $create->assertStatus(201);

        $detail = SaleDetail::latest('id')
            ->firstOrFail();

        $service = $this->compatibility();

        $this->assertSame(
            2,
            $service->salesDepletionQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $increase = $this
            ->withHeaders($headers)
            ->putJson(
                '/api/sales/detail/' . $detail->id,
                ['quantity' => 4]
            );

        $increase->assertStatus(200);

        $this->assertSame(
            4,
            $service->salesDepletionQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $decrease = $this
            ->withHeaders($headers)
            ->putJson(
                '/api/sales/detail/' . $detail->id,
                ['quantity' => 1]
            );

        $decrease->assertStatus(200);

        $this->assertSame(
            1,
            $service->salesDepletionQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $remove = $this
            ->withHeaders($headers)
            ->deleteJson(
                '/api/sales/detail/' . $detail->id
            );

        $remove->assertStatus(204);

        $this->assertSame(
            0,
            $service->salesDepletionQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );

        $this->assertSame(
            0,
            $service->unallocatedQuantity(
                $ctx['product'],
                $ctx['company']->id
            )
        );
    }
}
