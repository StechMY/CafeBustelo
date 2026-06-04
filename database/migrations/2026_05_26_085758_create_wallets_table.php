<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type', 32);

            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('locked_balance', 18, 2)->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'type']);
            $table->index(['type', 'balance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};