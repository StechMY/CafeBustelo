<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_levels', function (Blueprint $table): void {
            $table->id();

            // 1 = B, 2 = C, 3 = D
            $table->unsignedInteger('level')->unique();

            $table->string('name', 64);

            $table->decimal('rate_percent', 8, 2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_levels');
    }
};