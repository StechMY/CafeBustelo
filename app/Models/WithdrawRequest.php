<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawRequest extends Model
{
    protected $fillable = [
        'user_id',
        'user_bank_account_id',

        'amount',
        'fee_amount',
        'net_amount',

        'vip_level_snapshot',
        'min_withdraw_amount_snapshot',
        'withdraw_fee_percent_snapshot',

        'bank_name',
        'bank_account_name',
        'bank_account_no',

        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_remark',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vip_level_snapshot' => 'integer',
            'min_withdraw_amount_snapshot' => 'decimal:2',
            'withdraw_fee_percent_snapshot' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(UserBankAccount::class, 'user_bank_account_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}