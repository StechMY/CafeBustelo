<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalaryClaimWindowSeeder extends Seeder
{
    public function run(): void
    {
        $windows = [
            [
                'type' => 'weekly',
                'is_enabled' => true,
                'day_of_week' => 0, // Sunday
                'day_of_month' => null,
                'month' => null,
                'day' => null,
                'start_time' => '10:00:00',
                'end_time' => '22:00:00',
                'description' => 'Weekly salary can be claimed every Sunday from 10:00 AM to 10:00 PM.',
            ],
            [
                'type' => 'monthly',
                'is_enabled' => true,
                'day_of_week' => null,
                'day_of_month' => 1,
                'month' => null,
                'day' => null,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'description' => 'Monthly salary can be claimed on the 1st day of every month.',
            ],
            [
                'type' => 'annual',
                'is_enabled' => true,
                'day_of_week' => null,
                'day_of_month' => null,
                'month' => 1,
                'day' => 1,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'description' => 'Annual salary can be claimed on 1 January every year.',
            ],
        ];

        foreach ($windows as $window) {
            DB::table('salary_claim_windows')->updateOrInsert(
                ['type' => $window['type']],
                array_merge($window, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}