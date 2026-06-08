<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_accounts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('bank_account_name');
            $table->string('bank_name');
            $table->string('bank_account_no');

            $table->string('status')->default('active');
            $table->boolean('is_locked')->default(true);

            $table->timestamp('bound_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique('bank_account_no');

            // Customer requested: one bank account name can only bind once.
            // Warning: this may block different users with the same real name.
            $table->unique('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_accounts');
    }
};