<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasure_key_entries', function (Blueprint $table): void {
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('treasure_key_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('admin_user_id');
            $table->dropColumn('remarks');
        });
    }
};
