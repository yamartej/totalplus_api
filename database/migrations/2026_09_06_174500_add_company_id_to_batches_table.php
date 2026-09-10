<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')
                ->nullable()
                ->after('id');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('set null');

            $table->index('company_id');
        });

        /*
         * Conservative legacy backfill:
         * assign a company only when the batch has products, every linked
         * product already has company_id, and all of them belong to the same
         * company. Ambiguous/legacy batches remain NULL for compatibility.
         */
        DB::table('batches')
            ->orderBy('id')
            ->chunkById(100, function ($batches) {
                foreach ($batches as $batch) {
                    $products = DB::table('products')
                        ->where('batch_id', $batch->id);

                    $totalProducts = (clone $products)->count();

                    if ($totalProducts === 0) {
                        continue;
                    }

                    $nullCompanyProducts = (clone $products)
                        ->whereNull('company_id')
                        ->count();

                    if ($nullCompanyProducts > 0) {
                        continue;
                    }

                    $companyIds = (clone $products)
                        ->whereNotNull('company_id')
                        ->distinct()
                        ->pluck('company_id');

                    if ($companyIds->count() !== 1) {
                        continue;
                    }

                    DB::table('batches')
                        ->where('id', $batch->id)
                        ->update([
                            'company_id' => (int) $companyIds->first(),
                        ]);
                }
            });
    }

    public function down()
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
