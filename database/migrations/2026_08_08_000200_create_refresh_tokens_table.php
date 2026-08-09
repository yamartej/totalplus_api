<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefreshTokensTable extends Migration
{
    public function up()
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('access_token_id')
                ->references('id')
                ->on('personal_access_tokens')
                ->onDelete('set null');

            $table->index(['user_id', 'revoked_at']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('refresh_tokens');
    }
}
