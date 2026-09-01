<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vip_level_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->timestamp('reached_at');
            $table->unique(['user_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_level_achievements');
    }
};
