<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_codes', function (Blueprint $table): void {
            $table->id();

            $table->string('code', 64)->unique();

            $table->foreignId('owner_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('issued_by_admin_user_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->string('type', 32)->default('admin')->index();

            $table->decimal('amount', 18, 2);

            $table->string('status', 32)
                ->default('unused')
                ->index();

            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->text('remark')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
            $table->index(['owner_user_id', 'type', 'status']);
            $table->index(['issued_by_admin_user_id', 'created_at']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_codes');
    }
};