<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\CreditCustomerDetail;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialTenantGuardPhase12Test extends TestCase
{
    use RefreshDatabase;

    private function actor(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $role = Roles::create(['name' => 'Financial ' . uniqid(), 'description' => 'Isolation test']);
        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => 'Test']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);
        return $user;
    }

    private function customer(Company $company, int $number): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'client_id' => $number,
            'name' => 'Test Customer',
            'address' => 'Test',
            'phone' => '12345678',
        ]);
    }

    public function test_cross_tenant_sales_reports_are_filtered(): void
    {
        $a = Company::create(['name' => 'Report A']);
        $b = Company::create(['name' => 'Report B']);
        $user = $this->actor($a, ['reports.view']);
        $saleA = Sale::create(['company_id' => $a->id, 'customer_id' => $this->customer($a, 60101)->id, 'total_amount' => 15, 'type_of_sale' => 'normal']);
        $saleB = Sale::create(['company_id' => $b->id, 'customer_id' => $this->customer($b, 60102)->id, 'total_amount' => 25, 'type_of_sale' => 'normal']);
        Sanctum::actingAs($user);
        $this->getJson('/api/reports/sales/period/2020-01-01/2099-12-31')
            ->assertOk()
            ->assertJsonFragment(['id' => $saleA->id])
            ->assertJsonMissing(['id' => $saleB->id]);
        $this->getJson('/api/reports/sales/customer/' . $saleB->customer_id)->assertExactJson([]);
    }

    public function test_credit_customer_records_cannot_be_read_or_deleted_cross_tenant(): void
    {
        $a = Company::create(['name' => 'Credit A']);
        $b = Company::create(['name' => 'Credit B']);
        $user = $this->actor($a, ['credits.view', 'credits.delete']);
        $customer = $this->customer($b, 60201);
        $payment = CreditCustomerDetail::create(['customer_id' => $customer->id, 'amount' => 12, 'payment_date' => now(), 'detail' => 'Secret']);
        Sanctum::actingAs($user);
        $this->getJson('/api/credit-customer-details')->assertOk()->assertJsonMissing(['id' => $payment->id]);
        $this->deleteJson('/api/credit-customer-details/' . $payment->id)->assertStatus(404);
        $this->assertDatabaseHas('credit_customer_details', ['id' => $payment->id]);
    }

    public function test_credit_customer_payment_requires_positive_amount_and_owned_customer(): void
    {
        $a = Company::create(['name' => 'Payment A']);
        $b = Company::create(['name' => 'Payment B']);
        $user = $this->actor($a, ['credits.create']);
        $foreign = $this->customer($b, 60301);
        Sanctum::actingAs($user);
        $this->postJson('/api/credit-customer-details', [
            'customer_id' => $foreign->id, 'amount' => 10, 'payment_date' => '2026-10-25'
        ])->assertStatus(404);
        $this->postJson('/api/credit-customer-details', [
            'customer_id' => $foreign->id, 'amount' => -5, 'payment_date' => '2026-10-25'
        ])->assertStatus(422);
    }


    public function test_void_refuses_sale_with_payments_and_preserves_ledger(): void
    {
        $a = Company::create(['name' => 'Paid Sale Company']);
        $user = $this->actor($a, ['sales.delete']);
        $sale = Sale::create([
            'company_id' => $a->id,
            'customer_id' => $this->customer($a, 60501)->id,
            'total_amount' => 100,
            'type_of_sale' => 'credit',
        ]);
        $payment = \App\Models\CreditDetail::create([
            'sale_id' => $sale->id,
            'amount' => 20,
            'payment_date' => now(),
            'detail' => 'Deposit',
        ]);
        Sanctum::actingAs($user);
        $this->deleteJson('/api/sales/' . $sale->id)->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseHas('credit_details', ['id' => $payment->id]);
    }

    public function test_sale_header_total_cannot_be_overwritten_directly(): void
    {
        $a = Company::create(['name' => 'Sale A']);
        $user = $this->actor($a, ['sales.update']);
        $sale = Sale::create([
            'company_id' => $a->id,
            'customer_id' => $this->customer($a, 60401)->id,
            'total_amount' => 100,
            'type_of_sale' => 'normal',
        ]);
        Sanctum::actingAs($user);
        $this->putJson('/api/sales/' . $sale->id, ['total_amount' => 1])->assertStatus(422);
        $this->assertSame('100.00', number_format((float) $sale->fresh()->total_amount, 2, '.', ''));
    }
}
