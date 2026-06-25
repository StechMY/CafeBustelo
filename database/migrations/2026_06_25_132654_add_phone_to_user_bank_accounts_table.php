<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_bank_accounts', 'phone')) {
                $table->string('phone', 40)->nullable()->after('bank_account_no');
            }

            if (! Schema::hasColumn('user_bank_accounts', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('user_bank_accounts', 'phone_verified_at')) {
                $table->dropColumn('phone_verified_at');
            }

            if (Schema::hasColumn('user_bank_accounts', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};