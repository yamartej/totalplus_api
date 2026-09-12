<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Roles;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingTenancyPhase5Test extends TestCase
{
    use RefreshDatabase;

    private function grantPermissions(User $user, array $names): void
    {
        $role = Roles::firstOrCreate(
            ['name' => 'Phase 5 Purchasing Tenancy'],
            ['description' => 'Phase 5 purchasing tenancy test role']
        );

        $permissionIds = collect($names)
            ->map(function (string $name) {
                return Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Phase 5 purchasing tenancy test permission']
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
                    ->createToken('phase-5-purchasing-tenancy')
                    ->plainTextToken,
        ];
    }

    private function makeSupplier(
        string $name,
        ?int $companyId
    ): Supplier {
        $supplier = Supplier::create([
            'name' => $name,
            'phone' => '555-0100',
        ]);

        $supplier->company_id = $companyId;
        $supplier->save();

        return $supplier;
    }

    private function makePurchaseOrder(
        int $supplierId,
        ?int $companyId,
        string $tracking
    ): PurchaseOrder {
        $order = PurchaseOrder::create([
            'supplier_id' => $supplierId,
            'tracking_number' => $tracking,
            'shipping_cost' => 10,
        ]);

        $order->company_id = $companyId;
        $order->save();

        return $order;
    }

    private function context(): array
    {
        $companyA = Company::create([
            'name' => 'Phase 5 Purchasing Company A',
        ]);

        $companyB = Company::create([
            'name' => 'Phase 5 Purchasing Company B',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
        ]);

        $supplierA = $this->makeSupplier(
            'Supplier A',
            (int) $companyA->id
        );

        $supplierB = $this->makeSupplier(
            'Supplier B',
            (int) $companyB->id
        );

        $legacySupplier = $this->makeSupplier(
            'Legacy Supplier',
            null
        );

        $orderA = $this->makePurchaseOrder(
            (int) $supplierA->id,
            (int) $companyA->id,
            'A-001'
        );

        $orderB = $this->makePurchaseOrder(
            (int) $supplierB->id,
            (int) $companyB->id,
            'B-001'
        );

        $legacyOrder = $this->makePurchaseOrder(
            (int) $legacySupplier->id,
            null,
            'LEG-001'
        );

        return compact(
            'companyA',
            'companyB',
            'userA',
            'supplierA',
            'supplierB',
            'legacySupplier',
            'orderA',
            'orderB',
            'legacyOrder'
        );
    }

    public function test_supplier_index_excludes_other_company_and_keeps_legacy_visible(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['suppliers.view']
                )
            )
            ->getJson('/api/suppliers');

        $response->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue(
            $ids->contains((int) $ctx['supplierA']->id)
        );

        $this->assertTrue(
            $ids->contains((int) $ctx['legacySupplier']->id)
        );

        $this->assertFalse(
            $ids->contains((int) $ctx['supplierB']->id)
        );
    }

    public function test_new_supplier_is_owned_by_authenticated_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['suppliers.create']
                )
            )
            ->postJson('/api/suppliers', [
                'name' => 'Created Supplier',
                'phone' => '555-0200',
            ]);

        $response->assertStatus(201);

        $created = Supplier::where(
            'name',
            'Created Supplier'
        )->firstOrFail();

        $this->assertSame(
            (int) $ctx['companyA']->id,
            (int) $created->company_id
        );
    }

    public function test_other_company_supplier_cannot_be_read_by_id(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['suppliers.view']
                )
            )
            ->getJson(
                '/api/suppliers/' . $ctx['supplierB']->id
            );

        $response->assertStatus(404);
    }

    public function test_purchase_order_index_excludes_other_company_and_keeps_legacy_visible(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.view']
                )
            )
            ->getJson('/api/purchaseorders');

        $response->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue(
            $ids->contains((int) $ctx['orderA']->id)
        );

        $this->assertTrue(
            $ids->contains((int) $ctx['legacyOrder']->id)
        );

        $this->assertFalse(
            $ids->contains((int) $ctx['orderB']->id)
        );
    }

    public function test_new_purchase_order_is_owned_by_authenticated_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.create']
                )
            )
            ->postJson('/api/purchaseorders', [
                'supplier_id' => $ctx['supplierA']->id,
                'tracking_number' => 'A-NEW',
                'shipping_cost' => 15,
            ]);

        $response->assertStatus(201);

        $created = PurchaseOrder::where(
            'tracking_number',
            'A-NEW'
        )->firstOrFail();

        $this->assertSame(
            (int) $ctx['companyA']->id,
            (int) $created->company_id
        );
    }

    public function test_purchase_order_cannot_use_supplier_from_other_company(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.create']
                )
            )
            ->postJson('/api/purchaseorders', [
                'supplier_id' => $ctx['supplierB']->id,
                'tracking_number' => 'INVALID-CROSS-TENANT',
                'shipping_cost' => 20,
            ]);

        $response->assertStatus(404);

        $this->assertDatabaseMissing(
            'purchase_orders',
            [
                'tracking_number' => 'INVALID-CROSS-TENANT',
            ]
        );
    }

    public function test_other_company_purchase_order_cannot_be_read_by_id(): void
    {
        $ctx = $this->context();

        $response = $this
            ->withHeaders(
                $this->authHeaders(
                    $ctx['userA'],
                    ['purchaseorders.view']
                )
            )
            ->getJson(
                '/api/purchaseorders/' . $ctx['orderB']->id
            );

        $response->assertStatus(404);
    }
}
