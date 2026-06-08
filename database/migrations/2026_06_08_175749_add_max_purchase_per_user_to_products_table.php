<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'max_purchase_per_user')) {
                $table->unsignedInteger('max_purchase_per_user')
                    ->default(2)
                    ->after('duration_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'max_purchase_per_user')) {
                $table->dropColumn('max_purchase_per_user');
            }
        });
    }
};