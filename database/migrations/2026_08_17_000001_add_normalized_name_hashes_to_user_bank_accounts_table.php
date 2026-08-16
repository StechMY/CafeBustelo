<?php

use App\Support\BankAccountNameMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            $table->string('bank_account_name_match_hash', 64)
                ->nullable()
                ->after('bank_account_name');
            $table->string('bank_account_name_unique_hash', 64)
                ->nullable()
                ->after('bank_account_name_match_hash');
        });

        $accounts = DB::table('user_bank_accounts')
            ->select(['id', 'bank_account_name'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($account): string => BankAccountNameMatcher::matchHash($account->bank_account_name));

        foreach ($accounts as $matchHash => $matchingAccounts) {
            foreach ($matchingAccounts->values() as $index => $account) {
                DB::table('user_bank_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'bank_account_name_match_hash' => $matchHash,
                        'bank_account_name_unique_hash' => $index === 0
                            ? $matchHash
                            : BankAccountNameMatcher::legacyUniqueHash((int) $account->id, $matchHash),
                    ]);
            }
        }

        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            $table->string('bank_account_name_match_hash', 64)->nullable(false)->change();
            $table->string('bank_account_name_unique_hash', 64)->nullable(false)->change();
            $table->index('bank_account_name_match_hash', 'bank_account_name_match_hash_idx');
            $table->unique('bank_account_name_unique_hash', 'bank_account_name_unique_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            $table->dropIndex('bank_account_name_match_hash_idx');
            $table->dropUnique('bank_account_name_unique_hash_unique');
            $table->dropColumn([
                'bank_account_name_match_hash',
                'bank_account_name_unique_hash',
            ]);
        });
    }
};
