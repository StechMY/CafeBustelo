<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'check_in_status')) {
                $table->boolean('check_in_status')
                    ->default(true)
                    ->after('annual_salary_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'check_in_status')) {
                $table->dropColumn('check_in_status');
            }
        });
    }
};