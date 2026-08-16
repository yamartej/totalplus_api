<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProductWarehouseUniqueToInventoriesTable extends Migration
{
    public function up()
    {
        $duplicate = DB::table('inventories')
            ->select('product_id', 'warehouse_id')
            ->groupBy('product_id', 'warehouse_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot create inventory unique constraint: duplicate product/warehouse balances exist.'
            );
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->unique(
                ['product_id', 'warehouse_id'],
                'inventories_product_warehouse_unique'
            );
        });
    }

    public function down()
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventories_product_warehouse_unique');
        });
    }
}