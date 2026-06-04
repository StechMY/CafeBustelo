<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('type', 32)->default('link')->index();
            $table->string('link', 2048);

            $table->string('status', 32)->default('active')->index();

            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};