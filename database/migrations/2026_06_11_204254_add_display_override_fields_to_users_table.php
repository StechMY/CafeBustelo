<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'display_vip_level')) {
                $table->unsignedTinyInteger('display_vip_level')->nullable()->after('accumulated_recharge');
            }

            if (! Schema::hasColumn('users', 'display_active_team_count')) {
                $table->unsignedInteger('display_active_team_count')->nullable()->after('display_vip_level');
            }

            if (! Schema::hasColumn('users', 'display_accumulated_recharge')) {
                $table->decimal('display_accumulated_recharge', 18, 2)->nullable()->after('display_active_team_count');
            }

            if (! Schema::hasColumn('users', 'display_total_earning')) {
                $table->decimal('display_total_earning', 18, 2)->nullable()->after('display_accumulated_recharge');
            }

            if (! Schema::hasColumn('users', 'display_total_withdrawals')) {
                $table->decimal('display_total_withdrawals', 18, 2)->nullable()->after('display_total_earning');
            }

            if (! Schema::hasColumn('users', 'display_total_team_commission')) {
                $table->decimal('display_total_team_commission', 18, 2)->nullable()->after('display_total_withdrawals');
            }

            if (! Schema::hasColumn('users', 'display_total_team_recharge')) {
                $table->decimal('display_total_team_recharge', 18, 2)->nullable()->after('display_total_team_commission');
            }

            if (! Schema::hasColumn('users', 'display_total_users_count')) {
                $table->unsignedInteger('display_total_users_count')->nullable()->after('display_total_team_recharge');
            }

            if (! Schema::hasColumn('users', 'display_valid_users_count')) {
                $table->unsignedInteger('display_valid_users_count')->nullable()->after('display_total_users_count');
            }

            if (! Schema::hasColumn('users', 'display_b_count')) {
                $table->unsignedInteger('display_b_count')->nullable()->after('display_valid_users_count');
            }

            if (! Schema::hasColumn('users', 'display_b_active_count')) {
                $table->unsignedInteger('display_b_active_count')->nullable()->after('display_b_count');
            }

            if (! Schema::hasColumn('users', 'display_c_count')) {
                $table->unsignedInteger('display_c_count')->nullable()->after('display_b_active_count');
            }

            if (! Schema::hasColumn('users', 'display_c_active_count')) {
                $table->unsignedInteger('display_c_active_count')->nullable()->after('display_c_count');
            }

            if (! Schema::hasColumn('users', 'display_d_count')) {
                $table->unsignedInteger('display_d_count')->nullable()->after('display_c_active_count');
            }

            if (! Schema::hasColumn('users', 'display_d_active_count')) {
                $table->unsignedInteger('display_d_active_count')->nullable()->after('display_d_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'display_vip_level',
                'display_active_team_count',
                'display_accumulated_recharge',
                'display_total_earning',
                'display_total_withdrawals',
                'display_total_team_commission',
                'display_total_team_recharge',
                'display_total_users_count',
                'display_valid_users_count',
                'display_b_count',
                'display_b_active_count',
                'display_c_count',
                'display_c_active_count',
                'display_d_count',
                'display_d_active_count',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};