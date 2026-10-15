<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('golden_egg_entries', function (Blueprint $table): void {
            $table->timestamp('qualified_at')->nullable()->after('invited_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('golden_egg_entries', function (Blueprint $table): void {
            $table->dropColumn('qualified_at');
        });
    }
};
