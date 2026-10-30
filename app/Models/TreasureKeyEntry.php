<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasureKeyEntry extends Model
{
    protected $fillable = ['user_id', 'invited_user_id', 'user_product_id', 'keys', 'admin_user_id', 'remarks'];

    protected function casts(): array
    {
        return ['keys' => 'integer'];
    }
}
