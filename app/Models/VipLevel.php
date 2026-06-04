<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipLevel extends Model
{
    protected $fillable = [
        'level',
        'name',
        'required_accumulated_recharge',
        'required_active_team_size',
        'daily_check_in_amount',
        'weekly_salary',
        'monthly_salary',
        'team_subsidy',
        'annual_salary',
        'min_withdraw_amount',
        'position_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'required_accumulated_recharge' => 'decimal:2',
            'required_active_team_size' => 'integer',
            'daily_check_in_amount' => 'decimal:2',
            'weekly_salary' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'team_subsidy' => 'decimal:2',
            'annual_salary' => 'decimal:2',
            'min_withdraw_amount' => 'decimal:2',
        ];
    }

    public function salaryClaims(): HasMany
    {
        return $this->hasMany(SalaryClaim::class);
    }

    public function salaryAmountForType(string $type): float
    {
        return match ($type) {
            SalaryClaim::TYPE_WEEKLY => (float) $this->weekly_salary,
            SalaryClaim::TYPE_MONTHLY => (float) $this->monthly_salary,
            SalaryClaim::TYPE_ANNUAL => (float) $this->annual_salary,
            default => 0,
        };
    }

    public function teamSubsidyForType(string $type): float
    {
        return $type === SalaryClaim::TYPE_MONTHLY
            ? (float) $this->team_subsidy
            : 0;
    }

    public function totalSalaryForType(string $type): float
    {
        return $this->salaryAmountForType($type) + $this->teamSubsidyForType($type);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}