<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateColumnsInSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            // eliminar restricciones antes de eliminar columnas
            if (Schema::hasColumn('sales', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('sales', 'quantity')) {
                $table->dropColumn('quantity');
            }
            // agregar seller_id, pop_id y total
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('pop_id');
            $table->decimal('total', 8, 2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            // agregar product_id y quantity
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            // eliminar seller_id, pop_id y total
            $table->dropColumn('seller_id');
            $table->dropColumn('pop_id');
            $table->dropColumn('total');
        });
    }
}
