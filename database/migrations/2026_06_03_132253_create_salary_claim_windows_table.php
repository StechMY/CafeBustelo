<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_claim_windows', function (Blueprint $table): void {
            $table->id();

            $table->string('type', 32)->unique(); // weekly, monthly, annual

            $table->boolean('is_enabled')->default(true);

            // Weekly: 0 = Sunday, 1 = Monday, ... 6 = Saturday
            $table->unsignedTinyInteger('day_of_week')->nullable();

            // Monthly: 1 - 31
            $table->unsignedTinyInteger('day_of_month')->nullable();

            // Annual: 1 - 12
            $table->unsignedTinyInteger('month')->nullable();

            // Annual: 1 - 31
            $table->unsignedTinyInteger('day')->nullable();

            $table->time('start_time')->default('00:00:00');
            $table->time('end_time')->default('23:59:00');

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['type', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_claim_windows');
    }
};