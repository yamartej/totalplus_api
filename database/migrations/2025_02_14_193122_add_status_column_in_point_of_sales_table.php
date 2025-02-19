<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusColumnInPointOfSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('point_of_sales', function (Blueprint $table) {
            $table->enum('status', ['created', 'open', 'closed', 'inactive'])->default('created')->after('ubication');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('point_of_sales', function (Blueprint $table) {
            //
        });
    }
}
