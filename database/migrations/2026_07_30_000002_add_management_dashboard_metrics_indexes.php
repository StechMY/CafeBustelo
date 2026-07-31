<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table): void {
            $table->index(['status', 'reviewed_at'], 'recharge_status_reviewed_idx');
            $table->index(['user_id', 'status', 'reviewed_at'], 'recharge_user_status_reviewed_idx');
        });

        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->index(['status', 'reviewed_at'], 'withdraw_status_reviewed_idx');
            $table->index(['user_id', 'status', 'reviewed_at'], 'withdraw_user_status_reviewed_idx');
        });

        Schema::table('user_products', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'purchased_at'], 'user_product_user_status_purchased_idx');
            $table->index(['product_id', 'purchased_at'], 'user_product_product_purchased_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['is_virtual', 'created_at'], 'users_virtual_created_idx');
            $table->index(['status', 'is_virtual'], 'users_status_virtual_idx');
        });

        Schema::table('bonus_claims', function (Blueprint $table): void {
            $table->index(['user_id', 'claimed_at'], 'bonus_user_claimed_idx');
        });

        Schema::table('daily_income_claims', function (Blueprint $table): void {
            $table->index(['user_id', 'claimed_at'], 'daily_income_user_claimed_idx');
        });

        Schema::table('salary_claims', function (Blueprint $table): void {
            $table->index(['user_id', 'claimed_at'], 'salary_user_claimed_idx');
        });

        Schema::table('commission_records', function (Blueprint $table): void {
            $table->index(
                ['earner_user_id', 'status', 'created_at'],
                'commission_earner_status_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('commission_records', function (Blueprint $table): void {
            $table->dropIndex('commission_earner_status_created_idx');
        });

        Schema::table('salary_claims', function (Blueprint $table): void {
            $table->dropIndex('salary_user_claimed_idx');
        });

        Schema::table('daily_income_claims', function (Blueprint $table): void {
            $table->dropIndex('daily_income_user_claimed_idx');
        });

        Schema::table('bonus_claims', function (Blueprint $table): void {
            $table->dropIndex('bonus_user_claimed_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_virtual_created_idx');
            $table->dropIndex('users_status_virtual_idx');
        });

        Schema::table('user_products', function (Blueprint $table): void {
            $table->dropIndex('user_product_user_status_purchased_idx');
            $table->dropIndex('user_product_product_purchased_idx');
        });

        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->dropIndex('withdraw_status_reviewed_idx');
            $table->dropIndex('withdraw_user_status_reviewed_idx');
        });

        Schema::table('recharge_requests', function (Blueprint $table): void {
            $table->dropIndex('recharge_status_reviewed_idx');
            $table->dropIndex('recharge_user_status_reviewed_idx');
        });
    }
};
