<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales_history', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->decimal('total_amount', 8, 2);
            
            // Restricciones de clave foránea
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales_history', function (Blueprint $table) {
            
        });
    }
}
