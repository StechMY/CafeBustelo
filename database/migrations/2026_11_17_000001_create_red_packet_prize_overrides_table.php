<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_packet_prize_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prize_id')->constrained('red_packet_prizes');
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('draw_id')->nullable()->unique()->constrained('red_packet_draws')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'used_at', 'cancelled_at'], 'red_packet_override_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_packet_prize_overrides');
    }
};
