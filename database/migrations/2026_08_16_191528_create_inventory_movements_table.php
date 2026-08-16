<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryMovementsTable extends Migration
{
    public function up()
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');

            $table->string('type', 30);

            $table->integer('quantity_delta');
            $table->integer('balance_before');
            $table->integer('balance_after');

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(
                ['company_id', 'warehouse_id', 'product_id', 'created_at'],
                'inventory_movements_lookup_index'
            );

            $table->index(
                ['reference_type', 'reference_id'],
                'inventory_movements_reference_index'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_movements');
    }
}