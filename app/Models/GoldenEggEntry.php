<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldenEggEntry extends Model
{
    protected $fillable = ['user_id', 'invited_user_id', 'user_product_id', 'qualified_at', 'used_at'];

    protected function casts(): array
    {
        return ['qualified_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
