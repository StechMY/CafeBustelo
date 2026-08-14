<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_products', function (Blueprint $table): void {
            $table->timestamp('next_claim_at')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_products', function (Blueprint $table): void {
            $table->timestamp('next_claim_at')
                ->nullable(false)
                ->change();
        });
    }
};