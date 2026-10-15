<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('golden_egg_prizes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('slot')->unique();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('stock')->nullable();
            $table->timestamps();
        });

        Schema::create('golden_egg_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'used_at']);
        });

        Schema::create('golden_egg_draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prize_id')->constrained('golden_egg_prizes');
            $table->foreignId('entry_id')->nullable()->unique()->constrained('golden_egg_entries')->nullOnDelete();
            $table->uuid('request_id');
            $table->unsignedTinyInteger('egg_number');
            $table->string('prize_name', 100);
            $table->string('prize_type', 20);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_virtual')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('purchase_coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('golden_egg_draw_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('discount_percent', 5, 2);
            $table->timestamp('used_at')->nullable();
            $table->foreignId('user_product_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'used_at']);
        });

        foreach ([10, 20, 50, 100, 200, 5, 10, 15, 20] as $index => $value) {
            $isCash = $index < 5;
            DB::table('golden_egg_prizes')->insert([
                'slot' => $index + 1,
                'name' => $isCash ? 'Wallet reward '.$value : $value.'% purchase discount',
                'type' => $isCash ? 'cash' : 'coupon',
                'amount' => $isCash ? $value : 0,
                'discount_percent' => $isCash ? 0 : $value,
                'weight' => 1,
                'stock' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['ViewAny:GoldenEggPrize', 'View:GoldenEggPrize', 'Update:GoldenEggPrize', 'Manage:GoldenEgg'] as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission, 'guard_name' => 'admin',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_coupons');
        Schema::dropIfExists('golden_egg_draws');
        Schema::dropIfExists('golden_egg_entries');
        Schema::dropIfExists('golden_egg_prizes');
        DB::table('settings')->where('key', 'golden_egg_enabled')->delete();
        DB::table('permissions')->where('guard_name', 'admin')->whereIn('name', [
            'ViewAny:GoldenEggPrize', 'View:GoldenEggPrize', 'Update:GoldenEggPrize', 'Manage:GoldenEgg',
        ])->delete();
    }
};
