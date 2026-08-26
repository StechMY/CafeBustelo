<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MANAGE_PERMISSION = 'ManagePaymentGateway:Settings';

    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('gateway_release_delay_hours_snapshot')
                ->nullable()
                ->after('gateway_release_at');
        });

        DB::table('withdraw_requests')
            ->where('status', 'approved')
            ->whereNotNull('gateway_release_at')
            ->whereNull('gateway_release_delay_hours_snapshot')
            ->update(['gateway_release_delay_hours_snapshot' => 24]);

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['name' => self::MANAGE_PERMISSION, 'guard_name' => 'admin'],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', self::MANAGE_PERMISSION)
                ->where('guard_name', 'admin')
                ->delete();
        }

        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->dropColumn('gateway_release_delay_hours_snapshot');
        });
    }
};
