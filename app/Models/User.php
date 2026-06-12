<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'profile_photo_path',
        'phone',
        'password',
        'referrer_id',
        'referral_code',
        'status',
        'is_virtual',
        'order_status',
        'withdraw_status',
        'weekly_salary_status',
        'monthly_salary_status',
        'annual_salary_status',
        'check_in_status',
        'accumulated_recharge',
        'activated_at',
        'registered_ip',
        'last_login_ip',
        'last_login_at',

        'display_vip_level',
        'display_active_team_count',
        'display_accumulated_recharge',
        'display_total_earning',
        'display_total_withdrawals',
        'display_total_team_commission',
        'display_total_team_recharge',
        'display_total_users_count',
        'display_valid_users_count',
        'display_b_count',
        'display_b_active_count',
        'display_c_count',
        'display_c_active_count',
        'display_d_count',
        'display_d_active_count',

        'display_income_today',
        'display_yesterday_earnings',
        'display_team_income',
        'display_daily_income_total',
        'display_salary_income_total',

        'display_today_joined_count',
        'display_yesterday_joined_count',
        'display_today_recharge',
        'display_yesterday_recharge',
        'display_this_month_recharge',
        'display_last_month_recharge',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_virtual' => 'boolean',
            'order_status' => 'boolean',
            'withdraw_status' => 'boolean',
            'accumulated_recharge' => 'decimal:2',
            'weekly_salary_status' => 'boolean',
            'monthly_salary_status' => 'boolean',
            'annual_salary_status' => 'boolean',
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'check_in_status' => 'boolean',
            'display_vip_level' => 'integer',
            'display_active_team_count' => 'integer',
            'display_accumulated_recharge' => 'decimal:2',
            'display_total_earning' => 'decimal:2',
            'display_total_withdrawals' => 'decimal:2',
            'display_total_team_commission' => 'decimal:2',
            'display_total_team_recharge' => 'decimal:2',
            'display_total_users_count' => 'integer',
            'display_valid_users_count' => 'integer',
            'display_b_count' => 'integer',
            'display_b_active_count' => 'integer',
            'display_c_count' => 'integer',
            'display_c_active_count' => 'integer',
            'display_d_count' => 'integer',
            'display_d_active_count' => 'integer',
            'display_income_today' => 'decimal:2',
            'display_yesterday_earnings' => 'decimal:2',
            'display_team_income' => 'decimal:2',
            'display_daily_income_total' => 'decimal:2',
            'display_salary_income_total' => 'decimal:2',
            'display_today_joined_count' => 'integer',
            'display_yesterday_joined_count' => 'integer',
            'display_today_recharge' => 'decimal:2',
            'display_yesterday_recharge' => 'decimal:2',
            'display_this_month_recharge' => 'decimal:2',
            'display_last_month_recharge' => 'decimal:2',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referrer_id');
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(UserLoginLog::class);
    }

    public function adminTopUps(): HasMany
    {
        return $this->hasMany(AdminTopUp::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function bankAccount(): HasOne
    {
        return $this->hasOne(UserBankAccount::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(UserProduct::class);
    }
    public function bonusClaims(): HasMany
    {
        return $this->hasMany(\App\Models\BonusClaim::class);
    }

    public function activeProducts(): HasMany
    {
        return $this->hasMany(UserProduct::class)->where('status', 'active');
    }

    public function rechargeRequests(): HasMany
    {
        return $this->hasMany(RechargeRequest::class);
    }

    public function withdrawRequests(): HasMany
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    public function dailyIncomeClaims(): HasMany
    {
        return $this->hasMany(DailyIncomeClaim::class);
    }

    public function earnedCommissions(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'earner_user_id');
    }

    public function sourceCommissions(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'source_user_id');
    }

    public function ownedBonusCodes(): HasMany
    {
        return $this->hasMany(\App\Models\BonusCode::class, 'owner_user_id');
    }

    public function checkInRecords(): HasMany
    {
        return $this->hasMany(\App\Models\CheckInRecord::class);
    }

    public function salaryClaims(): HasMany
    {
        return $this->hasMany(SalaryClaim::class);
    }

    public function svipPurchases(): HasMany
    {
        return $this->hasMany(SvipPurchase::class);
    }

    public function activeSvipPurchase(): HasOne
    {
        return $this->hasOne(SvipPurchase::class)
            ->active()
            ->latestOfMany();
    }

    public function isSvipActive(): bool
    {
        return $this->activeSvipPurchase()->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canOrder(): bool
    {
        return $this->order_status === true;
    }

    public function canWithdraw(): bool
    {
        return $this->withdraw_status === true;
    }

    public function teamUsersByLevel(int $level): Collection
    {
        if ($level < 1) {
            return new Collection();
        }

        $parentIds = collect([$this->id]);

        for ($currentLevel = 1; $currentLevel <= $level; $currentLevel++) {
            $query = User::query()
                ->whereIn('referrer_id', $parentIds);

            if ($currentLevel === $level) {
                return $query
                    ->with(['referrer'])
                    ->orderByDesc('created_at')
                    ->get();
            }

            $parentIds = $query->pluck('id');

            if ($parentIds->isEmpty()) {
                return new Collection();
            }
        }

        return new Collection();
    }

    public function allDownlineUsers(?int $maxDepth = null): SupportCollection
    {
        $result = collect();
        $parentIds = collect([$this->id]);
        $depth = 1;

        while ($parentIds->isNotEmpty()) {
            if ($maxDepth !== null && $depth > $maxDepth) {
                break;
            }

            $users = User::query()
                ->with(['referrer'])
                ->whereIn('referrer_id', $parentIds)
                ->orderByDesc('created_at')
                ->get();

            if ($users->isEmpty()) {
                break;
            }

            foreach ($users as $user) {
                $user->setAttribute('team_depth', $depth);
                $result->push($user);
            }

            $parentIds = $users->pluck('id');
            $depth++;
        }

        return $result;
    }

    public function activeTeamSize(?int $maxDepth = 3): int
    {
        return $this->allDownlineUsers($maxDepth)
            ->where('status', 'active')
            ->count();
    }

    public function unlimitedActiveTeamSize(): int
    {
        return $this->allDownlineUsers(null)
            ->where('status', 'active')
            ->count();
    }

    public function getRechargeWalletBalanceAttribute(): float
    {
        return (float) $this->wallets()
            ->where('type', Wallet::TYPE_RECHARGE)
            ->value('balance');
    }

    public function getWithdrawableWalletBalanceAttribute(): float
    {
        return (float) $this->wallets()
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->value('balance');
    }

    public function getTotalCommissionAttribute(): float
    {
        return (float) $this->earnedCommissions()
            ->where('status', 'paid')
            ->sum('amount');
    }

    public function getTotalDailyIncomeAttribute(): float
    {
        return (float) $this->dailyIncomeClaims()
            ->sum('amount');
    }

    public function getTotalApprovedRechargeAttribute(): float
    {
        return (float) $this->rechargeRequests()
            ->where('status', 'approved')
            ->sum('amount');
    }

    public function getTotalApprovedWithdrawAttribute(): float
    {
        return (float) $this->withdrawRequests()
            ->where('status', 'approved')
            ->sum('amount');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (!$this->profile_photo_path) {
            return null;
        }

        return Storage::disk('do_spaces')->url($this->profile_photo_path);
    }

    public function currentVipLevel(): ?VipLevel
    {
        $activeTeamSize = $this->activeTeamSize();

        return VipLevel::query()
            ->where('status', 'active')
            ->where('required_accumulated_recharge', '<=', $this->accumulated_recharge)
            ->where('required_active_team_size', '<=', $activeTeamSize)
            ->orderByDesc('level')
            ->first();
    }

    public function getCurrentVipLevelNameAttribute(): string
    {
        $level = $this->currentVipLevel();

        return $level ? $level->name : 'LV1';
    }

    public function getCurrentVipLevelNumberAttribute(): int
    {
        $level = $this->currentVipLevel();

        return $level ? (int) $level->level : 1;
    }

}