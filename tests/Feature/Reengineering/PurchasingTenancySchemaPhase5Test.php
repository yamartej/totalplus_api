<?php

namespace Tests\Feature\Reengineering;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchasingTenancySchemaPhase5Test extends TestCase
{
    use RefreshDatabase;

    public function test_suppliers_table_has_company_id_for_tenant_ownership(): void
    {
        $this->assertTrue(
            Schema::hasColumn('suppliers', 'company_id'),
            'Phase 5 requires suppliers.company_id for tenant ownership.'
        );
    }

    public function test_purchase_orders_table_has_company_id_for_tenant_ownership(): void
    {
        $this->assertTrue(
            Schema::hasColumn('purchase_orders', 'company_id'),
            'Phase 5 requires purchase_orders.company_id for tenant ownership.'
        );
    }
}
