<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VipLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'level' => 1,
                'name' => 'LV1',
                'required_accumulated_recharge' => 0,
                'required_active_team_size' => 0,
                'daily_check_in_amount' => 0.10,
                'weekly_salary' => 0,
                'monthly_salary' => 0,
                'team_subsidy' => 0,
                'annual_salary' => 0,
                'min_withdraw_amount' => 0,
                'position_name' => null,
                'status' => 'active',
            ],
            [
                'level' => 2,
                'name' => 'LV2',
                'required_accumulated_recharge' => 300,
                'required_active_team_size' => 4,
                'daily_check_in_amount' => 0.60,
                'weekly_salary' => 6,
                'monthly_salary' => 0,
                'team_subsidy' => 0,
                'annual_salary' => 0,
                'min_withdraw_amount' => 0,
                'position_name' => null,
                'status' => 'active',
            ],
            [
                'level' => 3,
                'name' => 'LV3',
                'required_accumulated_recharge' => 1000,
                'required_active_team_size' => 15,
                'daily_check_in_amount' => 2,
                'weekly_salary' => 20,
                'monthly_salary' => 210,
                'team_subsidy' => 210,
                'annual_salary' => 0,
                'min_withdraw_amount' => 0,
                'position_name' => null,
                'status' => 'active',
            ],
            [
                'level' => 4,
                'name' => 'LV4',
                'required_accumulated_recharge' => 3000,
                'required_active_team_size' => 40,
                'daily_check_in_amount' => 5,
                'weekly_salary' => 40,
                'monthly_salary' => 550,
                'team_subsidy' => 550,
                'annual_salary' => 0,
                'min_withdraw_amount' => 0,
                'position_name' => null,
                'status' => 'active',
            ],
            [
                'level' => 5,
                'name' => 'LV5',
                'required_accumulated_recharge' => 9000,
                'required_active_team_size' => 150,
                'daily_check_in_amount' => 10,
                'weekly_salary' => 120,
                'monthly_salary' => 1600,
                'team_subsidy' => 1600,
                'annual_salary' => 20000,
                'min_withdraw_amount' => 0,
                'position_name' => 'Junior Manager',
                'status' => 'active',
            ],
            [
                'level' => 6,
                'name' => 'LV6',
                'required_accumulated_recharge' => 20000,
                'required_active_team_size' => 350,
                'daily_check_in_amount' => 25,
                'weekly_salary' => 300,
                'monthly_salary' => 4500,
                'team_subsidy' => 4500,
                'annual_salary' => 50000,
                'min_withdraw_amount' => 0,
                'position_name' => 'Senior Manager',
                'status' => 'active',
            ],
            [
                'level' => 7,
                'name' => 'LV7',
                'required_accumulated_recharge' => 50000,
                'required_active_team_size' => 700,
                'daily_check_in_amount' => 60,
                'weekly_salary' => 1000,
                'monthly_salary' => 18000,
                'team_subsidy' => 18000,
                'annual_salary' => 200000,
                'min_withdraw_amount' => 0,
                'position_name' => 'City Leader',
                'status' => 'active',
            ],
            [
                'level' => 8,
                'name' => 'LV8',
                'required_accumulated_recharge' => 100000,
                'required_active_team_size' => 1500,
                'daily_check_in_amount' => 150,
                'weekly_salary' => 2500,
                'monthly_salary' => 85000,
                'team_subsidy' => 85000,
                'annual_salary' => 1500000,
                'min_withdraw_amount' => 0,
                'position_name' => 'International Leader',
                'status' => 'active',
            ],
        ];

        foreach ($levels as $level) {
            DB::table('vip_levels')->updateOrInsert(
                ['level' => $level['level']],
                array_merge($level, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}