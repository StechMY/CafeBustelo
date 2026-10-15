<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('golden_egg_entries', function (Blueprint $table): void {
            $table->foreignId('invited_user_id')->nullable()->change();
            $table->foreignId('user_product_id')->nullable()->unique()
                ->constrained('user_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('golden_egg_entries')->whereNull('invited_user_id')->delete();

        Schema::table('golden_egg_entries', function (Blueprint $table): void {
            $table->dropUnique(['user_product_id']);
            $table->dropConstrainedForeignId('user_product_id');
            $table->foreignId('invited_user_id')->nullable(false)->change();
        });
    }
};
