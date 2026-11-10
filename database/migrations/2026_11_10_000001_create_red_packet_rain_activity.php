<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_packet_prizes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('slot')->unique();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('weight')->default(0);
            $table->unsignedInteger('stock')->nullable();
            $table->timestamps();
        });
        Schema::create('red_packet_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('award_key', 100);
            $table->date('earned_on');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'award_key']);
            $table->index(['user_id', 'used_at']);
            $table->index(['user_id', 'earned_on', 'source']);
        });
        Schema::create('red_packet_draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prize_id')->constrained('red_packet_prizes');
            $table->foreignId('entry_id')->nullable()->unique()->constrained('red_packet_entries')->nullOnDelete();
            $table->uuid('request_id');
            $table->string('prize_name', 100);
            $table->string('prize_type', 20);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_virtual')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
        });
        Schema::table('purchase_coupons', function (Blueprint $table): void {
            $table->foreignId('red_packet_draw_id')->nullable()->unique()->constrained()->cascadeOnDelete();
        });
        foreach ([
            ['iPhone 18 Pro Max 256GB', 'physical', 0, 0, 0, 0],
            ['Washing machine', 'physical', 5000000, 0, 1, 3],
            ['Bluetooth earbuds', 'physical', 600000, 0, 4, 5],
            ['300,000 VND', 'cash', 300000, 0, 12, 20],
            ['100,000 VND', 'cash', 100000, 0, 75, null],
            ['10% purchase discount', 'coupon', 0, 10, 8, 20],
        ] as $index => [$name, $type, $amount, $discount, $weight, $stock]) {
            DB::table('red_packet_prizes')->insert([
                'slot' => $index + 1, 'name' => $name, 'type' => $type, 'amount' => $amount,
                'discount_percent' => $discount, 'weight' => $weight, 'stock' => $stock,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('settings')->insertOrIgnore([
            ['key' => 'red_packet_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'red_packet_starts_at', 'value' => '2026-11-15 00:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach (['Manage:RedPacket', 'ViewAny:RedPacketPrize', 'View:RedPacketPrize', 'Update:RedPacketPrize', 'ViewAny:RedPacketDraw', 'View:RedPacketDraw'] as $permission) {
            DB::table('permissions')->insertOrIgnore(['name' => $permission, 'guard_name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('purchase_coupons')->whereNotNull('red_packet_draw_id')->delete();
        Schema::table('purchase_coupons', fn (Blueprint $table) => $table->dropConstrainedForeignId('red_packet_draw_id'));
        Schema::dropIfExists('red_packet_draws');
        Schema::dropIfExists('red_packet_entries');
        Schema::dropIfExists('red_packet_prizes');
        DB::table('settings')->whereIn('key', ['red_packet_enabled', 'red_packet_starts_at', 'red_packet_ends_at'])->delete();
        DB::table('permissions')->where('guard_name', 'admin')->whereIn('name', ['Manage:RedPacket', 'ViewAny:RedPacketPrize', 'View:RedPacketPrize', 'Update:RedPacketPrize', 'ViewAny:RedPacketDraw', 'View:RedPacketDraw'])->delete();
    }
};
