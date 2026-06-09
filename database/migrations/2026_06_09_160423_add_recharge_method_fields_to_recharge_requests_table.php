<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('recharge_requests', 'recharge_method_id')) {
                $table->foreignId('recharge_method_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('recharge_methods')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('recharge_requests', 'receipt_disk')) {
                $table->string('receipt_disk')->nullable()->after('receipt_path');
            }

            if (! Schema::hasColumn('recharge_requests', 'payment_method_name')) {
                $table->string('payment_method_name')->nullable()->after('receipt_disk');
            }

            if (! Schema::hasColumn('recharge_requests', 'payment_bank_name')) {
                $table->string('payment_bank_name')->nullable()->after('payment_method_name');
            }

            if (! Schema::hasColumn('recharge_requests', 'payment_account_name')) {
                $table->string('payment_account_name')->nullable()->after('payment_bank_name');
            }

            if (! Schema::hasColumn('recharge_requests', 'payment_account_no')) {
                $table->string('payment_account_no')->nullable()->after('payment_account_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('recharge_requests', 'recharge_method_id')) {
                $table->dropConstrainedForeignId('recharge_method_id');
            }

            foreach ([
                'payment_account_no',
                'payment_account_name',
                'payment_bank_name',
                'payment_method_name',
                'receipt_disk',
            ] as $column) {
                if (Schema::hasColumn('recharge_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};