<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleVoidAuditFields extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->index();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 255)->nullable();
        });

        Schema::table('sales_details', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->index();
            $table->unsignedBigInteger('removed_by')->nullable();
        });
    }

    public function down()
    {
        Schema::table('sales_details', function (Blueprint $table) {
            $table->dropIndex(['removed_at']);
            $table->dropColumn(['removed_at', 'removed_by']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['voided_at']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
    }
}
