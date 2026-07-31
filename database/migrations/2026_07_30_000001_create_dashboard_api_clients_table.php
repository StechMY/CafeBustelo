<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_api_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_api_clients');
    }
};
