<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'order_status')) {
                $table->boolean('order_status')
                    ->default(true)
                    ->after('is_virtual')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'withdraw_status')) {
                $table->boolean('withdraw_status')
                    ->default(true)
                    ->after('order_status')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'registered_ip')) {
                $table->string('registered_ip', 45)
                    ->nullable()
                    ->after('activated_at')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)
                    ->nullable()
                    ->after('registered_ip')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')
                    ->nullable()
                    ->after('last_login_ip')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'order_status',
                'withdraw_status',
                'registered_ip',
                'last_login_ip',
                'last_login_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};