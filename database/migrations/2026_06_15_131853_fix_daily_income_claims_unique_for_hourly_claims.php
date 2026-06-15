<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_income_claims', function (Blueprint $table): void {
            /*
             * Old rule:
             * One claim per product per date.
             *
             * This breaks hourly products because one product may claim
             * multiple times in the same day.
             */
            $table->dropUnique('daily_income_claims_user_product_id_claim_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_income_claims', function (Blueprint $table): void {

            $table->unique(
                ['user_product_id', 'claim_date'],
                'daily_income_claims_user_product_id_claim_date_unique'
            );
        });
    }
};