<?php

use App\Support\Qc168VietnamBank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_account_review_requests', function (Blueprint $table): void {
            $table->string('bank_code', 32)->nullable()->after('bank_name')->index();
        });

        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            $table->string('bank_code', 32)->nullable()->after('bank_name')->index();
        });

        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->string('bank_code', 32)->nullable()->after('bank_name');
            $table->string('gateway_provider')->nullable()->index();
            $table->string('payout_status', 32)->nullable()->index();
            $table->timestamp('gateway_release_at')->nullable()->index();
            $table->timestamp('gateway_submitted_at')->nullable();
            $table->timestamp('gateway_completed_at')->nullable();
            $table->timestamp('gateway_last_callback_at')->nullable();
            $table->timestamp('gateway_last_synced_at')->nullable();
            $table->string('gateway_current_merchant_order_id')->nullable()->index();
            $table->string('gateway_payment_status', 32)->nullable()->index();
            $table->decimal('gateway_final_amount', 18, 2)->nullable();
            $table->text('gateway_fail_reason')->nullable();
            $table->json('gateway_meta')->nullable();
        });

        Schema::create('withdrawal_payout_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('withdraw_request_id')
                ->constrained('withdraw_requests')
                ->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('provider')->index();
            $table->string('merchant_order_id')->unique();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 16);
            $table->string('payment_channel', 64);
            $table->string('bank_name_snapshot')->nullable();
            $table->string('bank_code_snapshot', 32);
            $table->string('bank_account_name_snapshot');
            $table->string('bank_account_no_snapshot');
            $table->string('gateway_order_uid')->nullable()->index();
            $table->string('gateway_payment_uid')->nullable()->index();
            $table->string('gateway_status', 32)->nullable()->index();
            $table->decimal('gateway_final_amount', 18, 2)->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('provider_response')->nullable();
            $table->json('provider_meta')->nullable();
            $table->timestamps();

            $table->unique(['withdraw_request_id', 'attempt_no']);
        });

        Schema::table('payment_gateway_events', function (Blueprint $table): void {
            $table->foreignId('withdraw_request_id')
                ->nullable()
                ->after('recharge_request_id')
                ->constrained('withdraw_requests')
                ->nullOnDelete();
            $table->foreignId('withdrawal_payout_attempt_id')
                ->nullable()
                ->after('withdraw_request_id')
                ->constrained('withdrawal_payout_attempts')
                ->nullOnDelete();
            $table->foreignId('actor_admin_user_id')
                ->nullable()
                ->after('withdrawal_payout_attempt_id')
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->string('source', 64)->nullable()->after('actor_admin_user_id')->index();
            $table->text('reason')->nullable()->after('source');
            $table->boolean('signature_valid')->nullable()->change();
        });

        $this->backfillBankCodes('bank_account_review_requests');
        $this->backfillBankCodes('user_bank_accounts');
        $this->backfillBankCodes('withdraw_requests');

        DB::table('withdraw_requests')
            ->where('status', 'approved')
            ->whereNull('payout_status')
            ->update([
                'payout_status' => 'completed',
                'gateway_completed_at' => DB::raw('reviewed_at'),
            ]);

        if (Schema::hasTable('permissions')) {
            foreach (['RetryGateway:Withdrawal', 'CancelGateway:Withdrawal', 'SyncGateway:Withdrawal'] as $name) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'admin'],
                    ['updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', [
                    'RetryGateway:Withdrawal',
                    'CancelGateway:Withdrawal',
                    'SyncGateway:Withdrawal',
                ])
                ->delete();
        }

        DB::table('payment_gateway_events')->whereNull('signature_valid')->delete();

        Schema::table('payment_gateway_events', function (Blueprint $table): void {
            $table->boolean('signature_valid')->nullable(false)->change();
            $table->dropConstrainedForeignId('actor_admin_user_id');
            $table->dropConstrainedForeignId('withdrawal_payout_attempt_id');
            $table->dropConstrainedForeignId('withdraw_request_id');
            $table->dropColumn(['source', 'reason']);
        });

        Schema::dropIfExists('withdrawal_payout_attempts');

        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'bank_code',
                'gateway_provider',
                'payout_status',
                'gateway_release_at',
                'gateway_submitted_at',
                'gateway_completed_at',
                'gateway_last_callback_at',
                'gateway_last_synced_at',
                'gateway_current_merchant_order_id',
                'gateway_payment_status',
                'gateway_final_amount',
                'gateway_fail_reason',
                'gateway_meta',
            ]);
        });

        Schema::table('user_bank_accounts', function (Blueprint $table): void {
            $table->dropColumn('bank_code');
        });

        Schema::table('bank_account_review_requests', function (Blueprint $table): void {
            $table->dropColumn('bank_code');
        });
    }

    private function backfillBankCodes(string $table): void
    {
        DB::table($table)
            ->select(['id', 'bank_name'])
            ->whereNull('bank_code')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $bankCode = Qc168VietnamBank::codeForName($row->bank_name);

                    if ($bankCode !== null) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['bank_code' => $bankCode]);
                    }
                }
            });
    }
};
