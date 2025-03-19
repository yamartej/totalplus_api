<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveColumnsInSalesHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales_history', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('total_amount');
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
            $table->unsignedBigInteger('customer_id');
            $table->decimal('total_amount', 8, 2);
        });
    }
}
