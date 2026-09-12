<?php

namespace Tests\Feature\Reengineering;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseReceiptSchemaPhase5Test extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_receipts_table_has_transactional_receipt_contract(): void
    {
        $this->assertTrue(
            Schema::hasTable('purchase_receipts'),
            'Phase 5 requires purchase_receipts for one-time transactional receiving.'
        );

        foreach ([
            'company_id',
            'purchase_order_id',
            'warehouse_id',
            'received_by_user_id',
            'received_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('purchase_receipts', $column),
                "purchase_receipts.{$column} is required by the Phase 5 receipt contract."
            );
        }
    }
}
