<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'display_wallet_total_credit')) {
                $table->decimal('display_wallet_total_credit', 18, 2)->nullable();
            }

            if (! Schema::hasColumn('users', 'display_wallet_total_debit')) {
                $table->decimal('display_wallet_total_debit', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'display_wallet_total_credit',
                'display_wallet_total_debit',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};