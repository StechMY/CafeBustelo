<?php

namespace Database\Seeders;

use App\Models\SvipPackage;
use Illuminate\Database\Seeder;

class SvipPackageSeeder extends Seeder
{
    public function run(): void
    {
        SvipPackage::query()->firstOrCreate(
            ['name' => 'SVIP'],
            [
                'price' => 783000,
                'duration_days' => null,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Auto claim daily income.',
            ],
        );
    }
}