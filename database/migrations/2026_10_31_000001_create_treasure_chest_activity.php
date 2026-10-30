<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasure_key_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_product_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('keys');
            $table->timestamps();
        });

        Schema::create('treasure_chest_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('chest_number');
            $table->unsignedInteger('threshold');
            $table->decimal('amount', 16, 2)->default(0);
            $table->unsignedInteger('keys')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'chest_number']);
        });

        Schema::table('purchase_coupons', function (Blueprint $table): void {
            $table->unsignedBigInteger('golden_egg_draw_id')->nullable()->change();
            $table->foreignId('treasure_chest_claim_id')->nullable()->unique()
                ->constrained()->cascadeOnDelete();
        });

        DB::table('permissions')->insertOrIgnore([
            'name' => 'Manage:TreasureChest', 'guard_name' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('purchase_coupons')->whereNotNull('treasure_chest_claim_id')->delete();
        Schema::table('purchase_coupons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('treasure_chest_claim_id');
            $table->unsignedBigInteger('golden_egg_draw_id')->nullable(false)->change();
        });
        Schema::dropIfExists('treasure_chest_claims');
        Schema::dropIfExists('treasure_key_entries');
        DB::table('settings')->whereIn('key', [
            'treasure_chest_enabled', 'treasure_chest_starts_at', 'treasure_chest_ends_at',
        ])->delete();
        DB::table('permissions')->where('name', 'Manage:TreasureChest')->where('guard_name', 'admin')->delete();
    }
};
