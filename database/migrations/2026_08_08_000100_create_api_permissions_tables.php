<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiPermissionsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');

                $table->primary(['role_id', 'permission_id']);

                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('cascade');

                $table->foreign('permission_id')
                    ->references('id')
                    ->on('permissions')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
}
