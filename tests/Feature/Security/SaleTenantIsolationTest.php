<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Roles;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private static int $clientSequence = 81000000;

    private function roleWithSalesPermissions(): Roles
    {
        $role = Roles::create([
            'name' => 'Sales Tenant Test ' . uniqid(),
            'description' => 'Sales tenant isolation test role',
        ]);

        foreach ([
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.delete',
        ] as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'Sales tenant test permission']
            );

            $role->permissions()
                ->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    private function user(
        Company $company,
        bool $withPermissions = false
    ): User {
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        if ($withPermissions) {
            $role = $this->roleWithSalesPermissions();
            $user->roles()->attach($role->id);
        }

        return $user->fresh();
    }

    private function customer(Company $company): Customer
    {
        self::$clientSequence++;

        return Customer::create([
            'company_id' => $company->id,
            'client_id' => self::$clientSequence,
            'name' => 'Customer ' . self::$clientSequence,
            'address' => 'Testing',
            'phone' => '0000000000',
        ]);
    }

    private function pop(Company $company): PointOfSale
    {
        return PointOfSale::create([
            'company_id' => $company->id,
            'identifier' => 'POS-' . uniqid(),
            'ubication' => 'Testing',
            'status' => 'created',
        ]);
    }

    private function productInventory(
        Company $company,
        int $quantity = 10
    ): array {
        $warehouse = Warehouse::create([
            'name' => 'Warehouse ' . uniqid(),
            'description' => 'Testing',
            'address' => 'Testing',
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Product ' . uniqid(),
            'price' => 100,
            'final_cost' => 100,
            'wholesale_final_cost' => 90,
        ]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
        ]);

        return [$product, $inventory];
    }

    private function sale(
        Company $company,
        User $seller = null
    ): Sale {
        $seller = $seller ?: $this->user($company);

        return Sale::create([
            'company_id' => $company->id,
            'customer_id' => $this->customer($company)->id,
            'seller_id' => $seller->id,
            'pop_id' => $this->pop($company)->id,
            'total_amount' => 100,
            'type_of_sale' => 'normal',
        ]);
    }

    private function payload(
        Customer $customer,
        User $seller,
        PointOfSale $pop,
        Product $product
    ): array {
        return [
            'client_id' => $customer->id,
            'seller_id' => $seller->id,
            'pop_id' => $pop->id,
            'total' => 100,
            'type_of_sale' => 'normal',
            'carts' => [
                [
                    'productId' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];
    }

    public function test_company_user_only_lists_own_sales(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $ownSale = $this->sale($companyA, $actor);
        $foreignSale = $this->sale($companyB);

        Sanctum::actingAs($actor);

        $this->getJson('/api/sales')
            ->assertOk()
            ->assertJsonFragment(['id' => $ownSale->id])
            ->assertJsonMissing(['id' => $foreignSale->id]);
    }

    public function test_sale_is_created_in_authenticated_company(): void
    {
        $company = Company::create(['name' => 'Company A']);
        $actor = $this->user($company, true);
        $customer = $this->customer($company);
        $pop = $this->pop($company);
        [$product, $inventory] =
            $this->productInventory($company, 10);

        Sanctum::actingAs($actor);

        $this->postJson(
            '/api/sales',
            $this->payload(
                $customer,
                $actor,
                $pop,
                $product
            )
        )
            ->assertCreated()
            ->assertJsonPath('company_id', $company->id);

        $this->assertSame(
            9,
            (int) $inventory->fresh()->quantity
        );
    }

    public function test_sale_rejects_customer_from_other_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $foreignCustomer = $this->customer($companyB);
        $pop = $this->pop($companyA);
        [$product] = $this->productInventory($companyA);

        Sanctum::actingAs($actor);

        $this->postJson(
            '/api/sales',
            $this->payload(
                $foreignCustomer,
                $actor,
                $pop,
                $product
            )
        )->assertStatus(404);
    }

    public function test_sale_rejects_seller_from_other_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $foreignSeller = $this->user($companyB);
        $customer = $this->customer($companyA);
        $pop = $this->pop($companyA);
        [$product] = $this->productInventory($companyA);

        Sanctum::actingAs($actor);

        $this->postJson(
            '/api/sales',
            $this->payload(
                $customer,
                $foreignSeller,
                $pop,
                $product
            )
        )->assertStatus(404);
    }

    public function test_sale_rejects_pos_from_other_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $customer = $this->customer($companyA);
        $foreignPop = $this->pop($companyB);
        [$product] = $this->productInventory($companyA);

        Sanctum::actingAs($actor);

        $this->postJson(
            '/api/sales',
            $this->payload(
                $customer,
                $actor,
                $foreignPop,
                $product
            )
        )->assertStatus(404);
    }

    public function test_sale_cannot_mutate_inventory_of_other_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $customer = $this->customer($companyA);
        $pop = $this->pop($companyA);
        [$product, $foreignInventory] =
            $this->productInventory($companyB, 10);

        Sanctum::actingAs($actor);

        $this->postJson(
            '/api/sales',
            $this->payload(
                $customer,
                $actor,
                $pop,
                $product
            )
        )->assertStatus(422);

        $this->assertSame(
            10,
            (int) $foreignInventory->fresh()->quantity
        );
    }

    public function test_company_user_cannot_view_other_company_sale(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $foreignSale = $this->sale($companyB);

        Sanctum::actingAs($actor);

        $this->getJson('/api/sales/' . $foreignSale->id)
            ->assertStatus(404);
    }

    public function test_company_user_cannot_update_other_company_sale(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $foreignSale = $this->sale($companyB);

        Sanctum::actingAs($actor);

        $this->putJson('/api/sales/' . $foreignSale->id, [
            'total_amount' => 999,
        ])->assertStatus(404);

        $this->assertSame(
            '100.00',
            (string) $foreignSale->fresh()->total_amount
        );
    }

    public function test_company_user_cannot_delete_other_company_sale(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $foreignSale = $this->sale($companyB);

        Sanctum::actingAs($actor);

        $this->deleteJson('/api/sales/' . $foreignSale->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('sales', [
            'id' => $foreignSale->id,
        ]);
    }

    public function test_company_routes_reject_cross_company_id(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $actor = $this->user($companyA, true);
        $this->sale($companyB);

        Sanctum::actingAs($actor);

        $this->getJson(
            '/api/sales-by-company/' . $companyB->id
        )->assertStatus(403);

        $this->getJson(
            '/api/sales/credit-type-by-customer-by-company/'
            . $companyB->id
        )->assertStatus(403);

        $this->getJson(
            '/api/sales/credit-note-list-by-company/'
            . $companyB->id
        )->assertStatus(403);
    }

    public function test_global_user_requires_cross_company_permission(): void
    {
        $company = Company::create(['name' => 'Company A']);

        $globalUser = User::factory()->create([
            'company_id' => null,
        ]);

        $role = $this->roleWithSalesPermissions();
        $globalUser->roles()->attach($role->id);

        $this->sale($company);

        Sanctum::actingAs($globalUser);

        $this->getJson('/api/sales')
            ->assertStatus(403);
    }
}
