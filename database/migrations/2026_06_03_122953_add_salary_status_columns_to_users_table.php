<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'weekly_salary_status')) {
                $table->boolean('weekly_salary_status')->default(true)->after('withdraw_status');
            }

            if (! Schema::hasColumn('users', 'monthly_salary_status')) {
                $table->boolean('monthly_salary_status')->default(true)->after('weekly_salary_status');
            }

            if (! Schema::hasColumn('users', 'annual_salary_status')) {
                $table->boolean('annual_salary_status')->default(true)->after('monthly_salary_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'weekly_salary_status',
                'monthly_salary_status',
                'annual_salary_status',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};