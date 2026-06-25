<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_otps', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 40)->index();
            $table->string('purpose', 50)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->string('sms_uid', 64)->nullable();
            $table->string('sms_code', 20)->nullable();
            $table->string('sms_desc')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_otps');
    }
};