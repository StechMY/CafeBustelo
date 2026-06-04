<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->decimal('price', 18, 2);
            $table->decimal('daily_income', 18, 2);
            $table->unsignedInteger('duration_days');

            $table->decimal('upgrade_bonus', 18, 2)->default(0);
            $table->decimal('invite_bonus', 18, 2)->default(0);
            $table->string('image_path')
                ->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};