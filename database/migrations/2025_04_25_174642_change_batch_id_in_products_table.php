<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeBatchIdInProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            //Modificar el campo batch_id para que sea nullable
            $table->unsignedBigInteger('batch_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            //
            // Revertir el cambio, haciendo el campo batch_id no nullable
            $table->unsignedBigInteger('batch_id')->nullable(false)->change();
        });
    }
}
