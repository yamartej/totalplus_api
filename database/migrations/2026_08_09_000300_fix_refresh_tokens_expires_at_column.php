<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixRefreshTokensExpiresAtColumn extends Migration
{
    public function up()
    {
        DB::statement(
            'ALTER TABLE `refresh_tokens` ' .
            'MODIFY `expires_at` DATETIME NOT NULL'
        );
    }

    public function down()
    {
        DB::statement(
            'ALTER TABLE `refresh_tokens` ' .
            'MODIFY `expires_at` TIMESTAMP NOT NULL ' .
            'DEFAULT CURRENT_TIMESTAMP'
        );
    }
}
