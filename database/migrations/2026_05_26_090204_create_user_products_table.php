<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_products', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('price_snapshot', 18, 2);
            $table->decimal('daily_income_snapshot', 18, 2);
            $table->unsignedInteger('duration_days_snapshot');

            $table->unsignedInteger('claimed_days')->default(0);
            $table->unsignedInteger('missed_days')->default(0);

            $table->timestamp('purchased_at');
            $table->timestamp('next_claim_at');
            $table->timestamp('expires_at');

            $table->string('status', 32)->default('active')->index();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['next_claim_at', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_products');
    }
};