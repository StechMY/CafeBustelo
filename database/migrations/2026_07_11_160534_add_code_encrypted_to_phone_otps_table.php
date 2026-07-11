<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_otps', function (Blueprint $table): void {
            if (! Schema::hasColumn('phone_otps', 'code_encrypted')) {
                $table->text('code_encrypted')->nullable()->after('code_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('phone_otps', function (Blueprint $table): void {
            if (Schema::hasColumn('phone_otps', 'code_encrypted')) {
                $table->dropColumn('code_encrypted');
            }
        });
    }
};