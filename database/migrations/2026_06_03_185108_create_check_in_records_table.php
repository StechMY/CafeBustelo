<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_in_records', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('vip_level_id')
                ->nullable()
                ->constrained('vip_levels')
                ->nullOnDelete();

            $table->date('claim_date');

            $table->unsignedInteger('vip_level_snapshot')->default(1);
            $table->decimal('amount', 18, 2)->default(0);

            $table->timestamp('claimed_at');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'claim_date']);
            $table->index(['user_id', 'claimed_at']);
            $table->index(['claim_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_in_records');
    }
};