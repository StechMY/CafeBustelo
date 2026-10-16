<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ViewAny:GoldenEggDraw', 'View:GoldenEggDraw'] as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'admin',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('guard_name', 'admin')
            ->whereIn('name', ['ViewAny:GoldenEggDraw', 'View:GoldenEggDraw'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
