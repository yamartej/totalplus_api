<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseReceiptsTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('received_by_user_id')->nullable();

            $table->timestamp('received_at');
            $table->timestamps();

            /*
             * Phase 5 starts with complete, one-time receiving per purchase
             * order. The unique key is the structural idempotency guard:
             * a purchase order cannot be posted to inventory twice.
             */
            $table->unique(
                'purchase_order_id',
                'purchase_receipts_purchase_order_unique'
            );

            $table->index(
                ['company_id', 'warehouse_id', 'received_at'],
                'purchase_receipts_tenant_lookup_index'
            );

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('purchase_orders')
                ->onDelete('restrict');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign('received_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_receipts');
    }
}
