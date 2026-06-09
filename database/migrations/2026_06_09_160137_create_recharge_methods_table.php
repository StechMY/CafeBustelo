<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recharge_methods', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_no')->nullable();

            $table->string('qr_image_path')->nullable();
            $table->string('qr_image_disk')->nullable();

            $table->text('instructions')->nullable();

            $table->string('status', 32)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharge_methods');
    }
};