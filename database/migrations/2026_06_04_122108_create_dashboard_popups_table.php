<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_popups', function (Blueprint $table): void {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('image_path', 2048)->nullable();

            $table->string('button_text')->nullable();
            $table->string('button_url', 2048)->nullable();

            $table->boolean('is_enabled')->default(true);

            // false = once per login session
            // true = show every dashboard visit
            $table->boolean('show_every_visit')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_popups');
    }
};