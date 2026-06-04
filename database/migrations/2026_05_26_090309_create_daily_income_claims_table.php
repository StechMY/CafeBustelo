<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_income_claims', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedInteger('claim_day_no');

            $table->decimal('amount', 18, 2);

            $table->date('claim_date');
            $table->timestamp('claimable_at');
            $table->timestamp('claimed_at');

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['user_product_id', 'claim_day_no']);
            $table->unique(['user_product_id', 'claim_date']);

            $table->index(['user_id', 'claim_date']);
            $table->index(['claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_income_claims');
    }
};