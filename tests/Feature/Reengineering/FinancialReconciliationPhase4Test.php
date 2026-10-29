<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\CreditCustomerDetail;
use App\Models\CreditDetail;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReconciliationPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function actor(?Company $company, array $permissions): User
    {
        $user = User::factory()->create([
            'company_id' => $company ? $company->id : null,
        ]);
        $role = Roles::create([
            'name' => 'Reconciliation ' . uniqid(),
            'description' => 'Phase 4 isolated finance test',
        ]);
        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['description' => 'Phase 4 test permission']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);
        return $user;
    }

    private function customer(Company $company, int $clientId): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'client_id' => $clientId,
            'name' => 'Cliente QA ' . $clientId,
            'address' => 'Test',
            'phone' => '123456',
        ]);
    }

    private function credit(Customer $customer, ?int $companyId, float $amount, bool $voided = false): Sale
    {
        return Sale::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'total_amount' => $amount,
            'type_of_sale' => 'credit',
            'voided_at' => $voided ? now() : null,
        ]);
    }

    public function test_finance_report_keeps_sale_payments_and_customer_deposits_separate(): void
    {
        $company = Company::create(['name' => 'Finance A']);
        $customer = $this->customer($company, 99001);
        $saleA = $this->credit($customer, $company->id, 100.00);
        $saleB = $this->credit($customer, null, 30.00); // legacy same-tenant sale
        $this->credit($customer, $company->id, 55.00, true); // voided excluded
        Sale::create([
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'total_amount' => 400,
            'type_of_sale' => 'normal',
        ]);

        foreach ([20, 15] as $amount) {
            CreditDetail::create([
                'sale_id' => $saleA->id,
                'amount' => $amount,
                'payment_date' => now(),
            ]);
        }
        CreditDetail::create([
            'sale_id' => $saleB->id,
            'amount' => 10,
            'payment_date' => now(),
        ]);
        CreditCustomerDetail::create([
            'customer_id' => $customer->id,
            'amount' => 12,
            'payment_date' => now(),
        ]);

        Sanctum::actingAs($this->actor($company, ['credits.view']));
        $this->getJson('/api/credits/reconciliation')
            ->assertOk()
            ->assertJsonPath('summary.credit_sales_count', 2)
            ->assertJsonPath('summary.credit_sales_total', '130.00')
            ->assertJsonPath('summary.sale_payments_total', '45.00')
            ->assertJsonPath('summary.sale_balance', '85.00')
            ->assertJsonPath('summary.customer_payments_unallocated', '12.00')
            ->assertJsonPath('customers.0.customer_id', $customer->id)
            ->assertJsonPath('customers.0.credit_sales_count', 2)
            ->assertJsonPath('customers.0.sale_balance', '85.00')
            ->assertJsonPath('customers.0.customer_payments_unallocated', '12.00');
    }

    public function test_reconciliation_never_exposes_another_company_even_with_explicit_customer_id(): void
    {
        $companyA = Company::create(['name' => 'Finance A']);
        $companyB = Company::create(['name' => 'Finance B']);
        $customerA = $this->customer($companyA, 99002);
        $customerB = $this->customer($companyB, 99003);
        $this->credit($customerA, $companyA->id, 25);
        $this->credit($customerB, $companyB->id, 999);

        Sanctum::actingAs($this->actor($companyA, ['credits.view']));

        $this->getJson('/api/credits/reconciliation')
            ->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('summary.credit_sales_total', '25.00')
            ->assertJsonMissing(['customer_id' => $customerB->id]);

        $this->getJson('/api/credits/reconciliation?company_id=' . $companyB->id)
            ->assertForbidden();

        $this->getJson('/api/credits/reconciliation?customer_id=' . $customerB->id)
            ->assertNotFound();
    }

    public function test_global_finance_operator_must_select_company_and_needs_cross_tenant_permission(): void
    {
        $company = Company::create(['name' => 'Finance Global']);
        $customer = $this->customer($company, 99004);
        $this->credit($customer, $company->id, 45);

        Sanctum::actingAs($this->actor(null, ['credits.view', 'tenant.cross_company']));
        $this->getJson('/api/credits/reconciliation')->assertStatus(422);
        $this->getJson('/api/credits/reconciliation?company_id=' . $company->id)
            ->assertOk()
            ->assertJsonPath('summary.credit_sales_total', '45.00');

        Sanctum::actingAs($this->actor(null, ['credits.view']));
        $this->getJson('/api/credits/reconciliation?company_id=' . $company->id)
            ->assertForbidden();
    }

    public function test_report_requires_permission_and_is_read_only(): void
    {
        $company = Company::create(['name' => 'Finance Protected']);
        $customer = $this->customer($company, 99005);
        $sale = $this->credit($customer, $company->id, 50);

        Sanctum::actingAs($this->actor($company, []));
        $this->getJson('/api/credits/reconciliation')->assertForbidden();

        Sanctum::actingAs($this->actor($company, ['credits.view']));
        $this->getJson('/api/credits/reconciliation?customer_id=' . $customer->id)
            ->assertOk()
            ->assertJsonCount(1, 'customers');
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'total_amount' => 50,
        ]);
    }
}
