<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\PointOfSale;
use App\Models\Roles;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalWriteCompanyContextPhase3Test extends TestCase
{
    use RefreshDatabase;

    private $pointOfSale;
    private $sale;

    protected function setUp(): void
    {
        if (getenv('DB_DATABASE') !==
            'totalplus_phase3_functional_qa_20261031_204833') {
            throw new \RuntimeException('QA database guard failed.');
        }

        parent::setUp();

        $company = Company::create([
            'name' => 'Global Write Guard QA',
        ]);

        $seller = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'client_id' => 990001,
            'name' => 'Global Guard Customer',
            'address' => 'QA',
            'phone' => '0000000000',
        ]);

        $this->pointOfSale = PointOfSale::create([
            'company_id' => $company->id,
            'identifier' => 'QA-GLOBAL-POS',
            'ubication' => 'QA',
            'status' => 'created',
        ]);

        $this->sale = Sale::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'pop_id' => $this->pointOfSale->id,
            'total_amount' => 100,
            'type_of_sale' => 'normal',
        ]);

        $globalUser = User::factory()->create([
            'company_id' => null,
        ]);

        $role = Roles::create([
            'name' => 'Global Write Guard QA Role',
            'description' => 'Isolated QA role',
        ]);

        $names = [
            'tenant.cross_company',
            'pops.update',
            'pops.delete',
            'sales.update',
            'sales.delete',
        ];

        foreach ($names as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'QA permission']
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        $globalUser->roles()->attach($role->id);

        Sanctum::actingAs($globalUser);
    }

    public function test_global_user_cannot_update_pos_without_company(): void
    {
        $this->putJson('/api/pops/' . $this->pointOfSale->id, [
            'identifier' => 'MODIFIED-POS',
            'ubication' => 'Modified',
        ])->assertStatus(422);

        $this->assertSame(
            'QA-GLOBAL-POS',
            $this->pointOfSale->fresh()->identifier
        );
    }

    public function test_global_user_cannot_delete_pos_without_company(): void
    {
        $this->deleteJson(
            '/api/pops/' . $this->pointOfSale->id
        )->assertStatus(422);

        $this->assertDatabaseHas('point_of_sales', [
            'id' => $this->pointOfSale->id,
        ]);
    }

    public function test_global_user_cannot_update_sale_without_company(): void
    {
        $original = $this->sale->fresh()->credit_note_detail;

        $this->putJson('/api/sales/' . $this->sale->id, [
            'credit_note_detail' => 'Modified without company',
        ])->assertStatus(422);

        $this->assertSame(
            $original,
            $this->sale->fresh()->credit_note_detail
        );
    }

    public function test_global_user_cannot_void_sale_without_company(): void
    {
        $this->deleteJson(
            '/api/sales/' . $this->sale->id
        )->assertStatus(422);

        $this->assertNull($this->sale->fresh()->voided_at);
    }
}
