<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('svip_purchases', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('svip_package_id')
                ->nullable()
                ->constrained('svip_packages')
                ->nullOnDelete();

            $table->decimal('amount', 18, 2);

            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();

            $table->string('status', 32)->default('active')->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('svip_purchases');
    }
};