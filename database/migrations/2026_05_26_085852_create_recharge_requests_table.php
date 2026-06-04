<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recharge_requests', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 18, 2);

            $table->string('receipt_path')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('reference_no')->nullable();

            $table->string('status', 32)->default('pending')->index();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_remark')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharge_requests');
    }
};