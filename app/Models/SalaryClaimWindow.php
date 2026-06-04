<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryClaimWindow extends Model
{
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_ANNUAL = 'annual';

    protected $fillable = [
        'type',
        'is_enabled',
        'day_of_week',
        'day_of_month',
        'month',
        'day',
        'start_time',
        'end_time',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'month' => 'integer',
            'day' => 'integer',
        ];
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_WEEKLY => 'Weekly',
            self::TYPE_MONTHLY => 'Monthly',
            self::TYPE_ANNUAL => 'Annual',
            default => ucfirst($this->type),
        };
    }
}