<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportPartialPurchaseReceipts extends Migration
{
    public function up()
    {
        /*
         * Old receipts are kept intact. Those with no line records are treated
         * as legacy COMPLETE receipts by PurchaseReceiptService.
         */
        Schema::create('purchase_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_receipt_id');
            $table->unsignedBigInteger('purchase_order_product_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->foreign('purchase_receipt_id', 'pr_lines_receipt_fk')
                ->references('id')->on('purchase_receipts')->onDelete('restrict');
            $table->foreign('purchase_order_product_id', 'pr_lines_order_line_fk')
                ->references('id')->on('purchase_order_products')->onDelete('restrict');
            $table->foreign('product_id', 'pr_lines_product_fk')
                ->references('id')->on('products')->onDelete('restrict');

            $table->unique(
                ['purchase_receipt_id', 'purchase_order_product_id'],
                'pr_lines_receipt_orderline_unique'
            );
            $table->index('purchase_order_product_id', 'pr_lines_orderline_idx');
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable();
            $table->unique(
                ['company_id', 'idempotency_key'],
                'purchase_receipts_tenant_key_unique'
            );
            $table->index(
                'purchase_order_id',
                'purchase_receipts_order_lookup_index'
            );
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropUnique('purchase_receipts_purchase_order_unique');
        });
    }

    public function down()
    {
        // Refuse a rollback that would silently discard receipt detail/history.
        if (DB::table('purchase_receipt_lines')->exists()) {
            throw new \RuntimeException(
                'Refusing to drop historical receipt lines; restore from a reviewed backup.'
            );
        }

        $duplicates = DB::table('purchase_receipts')
            ->select('purchase_order_id')
            ->groupBy('purchase_order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new \RuntimeException(
                'Cannot restore one-receipt-per-order constraint while partial receipts exist.'
            );
        }

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->unique(
                'purchase_order_id',
                'purchase_receipts_purchase_order_unique'
            );
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropIndex('purchase_receipts_order_lookup_index');
            $table->dropUnique('purchase_receipts_tenant_key_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::dropIfExists('purchase_receipt_lines');
    }
}
