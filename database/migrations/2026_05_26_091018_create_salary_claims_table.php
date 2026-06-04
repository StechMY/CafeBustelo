<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_claims', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('vip_level_id')
                ->nullable()
                ->constrained('vip_levels')
                ->nullOnDelete();

            $table->string('type', 32); // weekly, monthly, annual
            $table->string('period_key', 32);

            $table->unsignedInteger('vip_level_snapshot');

            $table->decimal('accumulated_recharge_snapshot', 18, 2)->default(0);
            $table->unsignedInteger('active_team_size_snapshot')->default(0);

            $table->decimal('salary_amount', 18, 2)->default(0);
            $table->decimal('team_subsidy_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);

            $table->timestamp('claimed_at');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'type', 'period_key']);

            $table->index(['user_id', 'type']);
            $table->index(['type', 'period_key']);
            $table->index(['claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_claims');
    }
};