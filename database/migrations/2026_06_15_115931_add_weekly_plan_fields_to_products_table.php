<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'product_type')) {
                $table->string('product_type')->default('normal')->after('code');
            }

            if (! Schema::hasColumn('products', 'available_days')) {
                $table->json('available_days')->nullable()->after('product_type');
            }

            if (! Schema::hasColumn('products', 'available_from')) {
                $table->dateTime('available_from')->nullable()->after('available_days');
            }

            if (! Schema::hasColumn('products', 'available_until')) {
                $table->dateTime('available_until')->nullable()->after('available_from');
            }

            if (! Schema::hasColumn('products', 'stock_quantity')) {
                $table->unsignedInteger('stock_quantity')->nullable()->after('available_until');
            }

            if (! Schema::hasColumn('products', 'sold_quantity')) {
                $table->unsignedInteger('sold_quantity')->default(0)->after('stock_quantity');
            }

            if (! Schema::hasColumn('products', 'claim_interval_hours')) {
                $table->unsignedInteger('claim_interval_hours')->default(24)->after('sold_quantity');
            }

            if (! Schema::hasColumn('products', 'total_claims')) {
                $table->unsignedInteger('total_claims')->nullable()->after('claim_interval_hours');
            }

            if (! Schema::hasColumn('products', 'extra_points')) {
                $table->unsignedInteger('extra_points')->default(0)->after('total_claims');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'product_type',
                'available_days',
                'available_from',
                'available_until',
                'stock_quantity',
                'sold_quantity',
                'claim_interval_hours',
                'total_claims',
                'extra_points',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};