<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table): void {
            $table->string('deposit_method')->default('manual')->index();
            $table->string('gateway_provider')->nullable()->index();
            $table->string('merchant_order_id')->nullable()->unique();
            $table->string('gateway_order_uid')->nullable()->index();
            $table->string('gateway_payment_uid')->nullable()->index();
            $table->string('gateway_payment_status')->nullable()->index();
            $table->text('gateway_payment_url')->nullable();
            $table->decimal('gateway_final_amount', 18, 2)->nullable();
            $table->string('gateway_currency')->nullable();
            $table->string('gateway_channel')->nullable();
            $table->text('gateway_fail_reason')->nullable();
            $table->timestamp('gateway_completed_at')->nullable();
            $table->timestamp('gateway_last_callback_at')->nullable();
            $table->timestamp('gateway_last_synced_at')->nullable();
            $table->json('gateway_meta')->nullable();
        });

        Schema::create('payment_gateway_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->index();
            $table->string('merchant_order_id')->nullable()->index();
            $table->foreignId('recharge_request_id')
                ->nullable()
                ->constrained('recharge_requests')
                ->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('payment_status')->nullable()->index();
            $table->boolean('signature_valid');
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_result')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['name' => 'SyncGateway:Recharge', 'guard_name' => 'admin'],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_events');

        Schema::table('recharge_requests', function (Blueprint $table): void {
            $table->dropUnique(['merchant_order_id']);
            $table->dropColumn([
                'deposit_method',
                'gateway_provider',
                'merchant_order_id',
                'gateway_order_uid',
                'gateway_payment_uid',
                'gateway_payment_status',
                'gateway_payment_url',
                'gateway_final_amount',
                'gateway_currency',
                'gateway_channel',
                'gateway_fail_reason',
                'gateway_completed_at',
                'gateway_last_callback_at',
                'gateway_last_synced_at',
                'gateway_meta',
            ]);
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'SyncGateway:Recharge')
                ->where('guard_name', 'admin')
                ->delete();
        }
    }
};
