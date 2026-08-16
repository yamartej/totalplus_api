<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NormalizeSalesDetailsSaleId extends Migration
{
    /**
     * Normaliza la FK histórica `sales_id` al nombre usado por
     * SaleDetail y SaleController: `sale_id`.
     */
    public function up()
    {
        if (!Schema::hasTable('sales_details')) {
            return;
        }

        if (Schema::hasColumn('sales_details', 'sales_id')
            && !Schema::hasColumn('sales_details', 'sale_id')) {

            Schema::table('sales_details', function (Blueprint $table) {
                $table->dropForeign(['sales_id']);
            });

            Schema::table('sales_details', function (Blueprint $table) {
                $table->renameColumn('sales_id', 'sale_id');
            });

            Schema::table('sales_details', function (Blueprint $table) {
                $table->foreign('sale_id')
                    ->references('id')
                    ->on('sales')
                    ->onDelete('set null');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('sales_details')) {
            return;
        }

        if (Schema::hasColumn('sales_details', 'sale_id')
            && !Schema::hasColumn('sales_details', 'sales_id')) {

            Schema::table('sales_details', function (Blueprint $table) {
                $table->dropForeign(['sale_id']);
            });

            Schema::table('sales_details', function (Blueprint $table) {
                $table->renameColumn('sale_id', 'sales_id');
            });

            Schema::table('sales_details', function (Blueprint $table) {
                $table->foreign('sales_id')
                    ->references('id')
                    ->on('sales')
                    ->onDelete('set null');
            });
        }
    }
}
