<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('withdraw_requests', 'user_bank_account_id')) {
                $table->foreignId('user_bank_account_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('user_bank_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('withdraw_requests', 'withdraw_fee_percent_snapshot')) {
                $table->decimal('withdraw_fee_percent_snapshot', 8, 2)
                    ->default(0)
                    ->after('min_withdraw_amount_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('withdraw_requests', 'user_bank_account_id')) {
                $table->dropConstrainedForeignId('user_bank_account_id');
            }

            if (Schema::hasColumn('withdraw_requests', 'withdraw_fee_percent_snapshot')) {
                $table->dropColumn('withdraw_fee_percent_snapshot');
            }
        });
    }
};