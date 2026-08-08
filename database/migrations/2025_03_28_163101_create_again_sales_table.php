<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgainSalesTable extends Migration
{
    /**
     * Normaliza la tabla histórica `sales` al esquema usado por la
     * aplicación actual.
     *
     * En una instalación limpia `sales` ya existe porque fue creada en 2023
     * y transformada parcialmente en febrero de 2025. La versión original
     * de esta migración intentaba crearla de nuevo y provocaba SQLSTATE[42S01].
     */
    public function up()
    {
        if (!Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('customer_id')->nullable();
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->onDelete('set null');

                $table->unsignedBigInteger('seller_id')->nullable();
                $table->foreign('seller_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->unsignedBigInteger('pop_id')->nullable();
                $table->foreign('pop_id')
                    ->references('id')
                    ->on('point_of_sales')
                    ->onDelete('set null');

                $table->decimal('total_amount', 8, 2)->nullable();
                $table->timestamps();
            });

            return;
        }

        /*
         * Camino usado por una reconstrucción limpia del historial:
         * la tabla de 2023 ya existe y la migración de febrero de 2025
         * ya eliminó product_id/quantity y agregó seller_id, pop_id y total.
         */

        if (
            Schema::hasColumn('sales', 'total')
            && !Schema::hasColumn('sales', 'total_amount')
        ) {
            Schema::table('sales', function (Blueprint $table) {
                $table->renameColumn('total', 'total_amount');
            });
        }

        // La FK de customer_id viene de la migración original de 2023.
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->unsignedBigInteger('seller_id')->nullable()->change();
            $table->unsignedBigInteger('pop_id')->nullable()->change();
            $table->decimal('total_amount', 8, 2)->nullable()->change();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('set null');

            $table->foreign('seller_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('pop_id')
                ->references('id')
                ->on('point_of_sales')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
}
