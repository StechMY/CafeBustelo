<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldenEggPrize extends Model
{
    protected $fillable = ['slot', 'name', 'type', 'amount', 'discount_percent', 'weight', 'stock'];

    protected function casts(): array
    {
        return [
            'slot' => 'integer', 'amount' => 'decimal:2', 'discount_percent' => 'decimal:2',
            'weight' => 'integer', 'stock' => 'integer',
        ];
    }
}
