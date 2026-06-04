<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'P1 Coffee Pack',
                'code' => 'P1',
                'price' => 275,
                'daily_income' => 13.20,
                'duration_days' => 42,
                'upgrade_bonus' => 12,
                'invite_bonus' => 25,
                'sort_order' => 1,
            ],
            [
                'name' => 'P3 Coffee Pack',
                'code' => 'P3',
                'price' => 800,
                'daily_income' => 40,
                'duration_days' => 43,
                'upgrade_bonus' => 20,
                'invite_bonus' => 35,
                'sort_order' => 2,
            ],
            [
                'name' => 'P5 Coffee Pack',
                'code' => 'P5',
                'price' => 2200,
                'daily_income' => 114.40,
                'duration_days' => 44,
                'upgrade_bonus' => 30,
                'invite_bonus' => 50,
                'sort_order' => 3,
            ],
            [
                'name' => 'P7 Coffee Pack',
                'code' => 'P7',
                'price' => 3900,
                'daily_income' => 206.70,
                'duration_days' => 45,
                'upgrade_bonus' => 50,
                'invite_bonus' => 70,
                'sort_order' => 4,
            ],
            [
                'name' => 'P9 Coffee Pack',
                'code' => 'P9',
                'price' => 6500,
                'daily_income' => 351,
                'duration_days' => 46,
                'upgrade_bonus' => 100,
                'invite_bonus' => 0,
                'sort_order' => 5,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['code' => $product['code']],
                array_merge($product, [
                    'status' => 'active',
                ])
            );
        }
    }
}