<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'display_income_today')) {
                $table->decimal('display_income_today', 18, 2)->nullable()->after('display_total_withdrawals');
            }

            if (! Schema::hasColumn('users', 'display_yesterday_earnings')) {
                $table->decimal('display_yesterday_earnings', 18, 2)->nullable()->after('display_income_today');
            }

            if (! Schema::hasColumn('users', 'display_team_income')) {
                $table->decimal('display_team_income', 18, 2)->nullable()->after('display_yesterday_earnings');
            }

            if (! Schema::hasColumn('users', 'display_daily_income_total')) {
                $table->decimal('display_daily_income_total', 18, 2)->nullable()->after('display_team_income');
            }

            if (! Schema::hasColumn('users', 'display_salary_income_total')) {
                $table->decimal('display_salary_income_total', 18, 2)->nullable()->after('display_daily_income_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'display_income_today',
                'display_yesterday_earnings',
                'display_team_income',
                'display_daily_income_total',
                'display_salary_income_total',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};