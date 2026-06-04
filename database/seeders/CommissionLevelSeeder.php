<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommissionLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'level' => 1,
                'name' => 'B Level',
                'rate_percent' => 11,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Direct downline commission.',
            ],
            [
                'level' => 2,
                'name' => 'C Level',
                'rate_percent' => 4,
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'Second level downline commission.',
            ],
            [
                'level' => 3,
                'name' => 'D Level',
                'rate_percent' => 2,
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'Third level downline commission.',
            ],
        ];

        foreach ($levels as $level) {
            DB::table('commission_levels')->updateOrInsert(
                ['level' => $level['level']],
                array_merge($level, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}