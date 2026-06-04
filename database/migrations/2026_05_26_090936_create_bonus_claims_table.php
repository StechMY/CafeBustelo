<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_claims', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('bonus_code_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type', 32);
            $table->decimal('amount', 18, 2);

            $table->timestamp('claimed_at');

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique('bonus_code_id');
            $table->index(['user_id', 'type']);
            $table->index(['claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_claims');
    }
};