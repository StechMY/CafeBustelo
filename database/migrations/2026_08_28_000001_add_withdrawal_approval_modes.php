<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ApproveManual:Withdrawal',
        'ApproveScheduledGateway:Withdrawal',
        'ApproveInstantGateway:Withdrawal',
    ];

    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->string('approval_mode', 32)
                ->nullable()
                ->after('status')
                ->index();
        });

        DB::table('withdraw_requests')
            ->where('status', 'approved')
            ->where('gateway_provider', 'qc168')
            ->whereNull('approval_mode')
            ->update(['approval_mode' => 'gateway_scheduled']);

        DB::table('withdraw_requests')
            ->where('status', 'approved')
            ->whereNull('approval_mode')
            ->update(['approval_mode' => 'manual']);

        if (Schema::hasTable('permissions')) {
            foreach (self::PERMISSIONS as $name) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'admin'],
                    ['updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', self::PERMISSIONS)
                ->delete();
        }

        Schema::table('withdraw_requests', function (Blueprint $table): void {
            $table->dropColumn('approval_mode');
        });
    }
};
