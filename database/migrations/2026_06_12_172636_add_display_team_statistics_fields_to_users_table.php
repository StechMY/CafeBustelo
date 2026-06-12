<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'display_today_joined_count')) {
                $table->unsignedInteger('display_today_joined_count')->nullable();
            }

            if (! Schema::hasColumn('users', 'display_yesterday_joined_count')) {
                $table->unsignedInteger('display_yesterday_joined_count')->nullable();
            }

            if (! Schema::hasColumn('users', 'display_today_recharge')) {
                $table->decimal('display_today_recharge', 18, 2)->nullable();
            }

            if (! Schema::hasColumn('users', 'display_yesterday_recharge')) {
                $table->decimal('display_yesterday_recharge', 18, 2)->nullable();
            }

            if (! Schema::hasColumn('users', 'display_this_month_recharge')) {
                $table->decimal('display_this_month_recharge', 18, 2)->nullable();
            }

            if (! Schema::hasColumn('users', 'display_last_month_recharge')) {
                $table->decimal('display_last_month_recharge', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'display_today_joined_count',
                'display_yesterday_joined_count',
                'display_today_recharge',
                'display_yesterday_recharge',
                'display_this_month_recharge',
                'display_last_month_recharge',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};