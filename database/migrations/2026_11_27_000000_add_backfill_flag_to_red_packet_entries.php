<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Runs before the invitation backfill on sites where it is still pending.
        Schema::table('red_packet_entries', function (Blueprint $table): void {
            $table->boolean('is_backfill')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('red_packet_entries', function (Blueprint $table): void {
            $table->dropColumn('is_backfill');
        });
    }
};
