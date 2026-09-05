<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionFieldsToSalesDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('sales_details', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')
                ->nullable()
                ->after('product_id');

            $table->decimal('unit_price', 12, 2)
                ->nullable()
                ->after('quantity');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('sales_details', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn([
                'warehouse_id',
                'unit_price',
            ]);
        });
    }
}
