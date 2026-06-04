<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_records', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('earner_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('source_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('source_user_product_id')
                ->constrained('user_products')
                ->cascadeOnDelete();

            $table->foreignId('daily_income_claim_id')
                ->constrained('daily_income_claims')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('generation_level');

            $table->decimal('source_daily_income', 18, 2);
            $table->decimal('percentage', 8, 4);
            $table->decimal('amount', 18, 2);

            $table->string('status', 32)->default('paid')->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique([
                'earner_user_id',
                'daily_income_claim_id',
                'generation_level',
            ], 'commission_unique_per_claim_level');

            $table->index(['earner_user_id', 'status']);
            $table->index(['source_user_id', 'generation_level']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_records');
    }
};