<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_products', 'claim_interval_hours_snapshot')) {
                $table->unsignedInteger('claim_interval_hours_snapshot')->nullable()->after('duration_days_snapshot');
            }

            if (! Schema::hasColumn('user_products', 'total_claims_snapshot')) {
                $table->unsignedInteger('total_claims_snapshot')->nullable()->after('claim_interval_hours_snapshot');
            }

            if (! Schema::hasColumn('user_products', 'product_type_snapshot')) {
                $table->string('product_type_snapshot')->nullable()->after('total_claims_snapshot');
            }

            if (! Schema::hasColumn('user_products', 'extra_points_snapshot')) {
                $table->unsignedInteger('extra_points_snapshot')->default(0)->after('product_type_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_products', function (Blueprint $table): void {
            foreach ([
                'claim_interval_hours_snapshot',
                'total_claims_snapshot',
                'product_type_snapshot',
                'extra_points_snapshot',
            ] as $column) {
                if (Schema::hasColumn('user_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};