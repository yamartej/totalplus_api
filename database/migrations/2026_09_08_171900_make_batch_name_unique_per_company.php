<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            /*
             * Batch names are tenant-local. The original schema enforced
             * global uniqueness on name, which prevents different companies
             * from reusing the same operational batch identifier.
             */
            $table->dropUnique('batches_name_unique');

            $table->unique(
                ['company_id', 'name'],
                'batches_company_id_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropUnique(
                'batches_company_id_name_unique'
            );

            /*
             * Restoring the legacy global constraint can fail if duplicate
             * names now exist across companies. That is preferable to
             * silently deleting or renaming tenant data during rollback.
             */
            $table->unique(
                'name',
                'batches_name_unique'
            );
        });
    }
};
