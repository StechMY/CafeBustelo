<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vip_levels', function (Blueprint $table) {
            $table->decimal('withdraw_fee_percent', 5, 2)->default(0)->after('min_withdraw_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vip_levels', function (Blueprint $table) {
            $table->dropColumn('withdraw_fee_percent');
        });
    }
};
