<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('referrer_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('referral_code', 32)
                ->unique()
                ->nullable()
                ->after('referrer_id');

            $table->string('phone', 32)
                ->nullable()
                ->index()
                ->after('username');

            $table->string('status', 32)
                ->default('inactive')
                ->index()
                ->after('password');

            $table->decimal('accumulated_recharge', 18, 2)
                ->default(0)
                ->after('status');

            $table->timestamp('activated_at')
                ->nullable()
                ->after('accumulated_recharge');

            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['referrer_id']);

            $table->dropIndex(['referrer_id', 'status']);

            $table->dropColumn([
                'referrer_id',
                'referral_code',
                'phone',
                'status',
                'accumulated_recharge',
                'activated_at',
            ]);
        });
    }
};