<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vip_levels', function (Blueprint $table): void {
            $table->id();

            $table->unsignedInteger('level')->unique();
            $table->string('name');

            $table->decimal('required_accumulated_recharge', 18, 2)->default(0);
            $table->unsignedInteger('required_active_team_size')->default(0);

            $table->decimal('daily_check_in_amount', 18, 2)->default(0);
            $table->decimal('weekly_salary', 18, 2)->default(0);
            $table->decimal('monthly_salary', 18, 2)->default(0);
            $table->decimal('annual_salary', 18, 2)->default(0);
            $table->decimal('team_subsidy', 18, 2)->default(0);

            $table->decimal('min_withdraw_amount', 18, 2)->default(0);

            $table->string('position_name')->nullable();
            $table->string('status', 32)->default('active')->index();

            $table->timestamps();

            $table->index(['status', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_levels');
    }
};