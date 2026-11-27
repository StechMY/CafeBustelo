<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class YdPayPayoutReturnEvidence extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ydpay_payout_return_evidence';

    protected $fillable = ['withdraw_request_id', 'return_reference', 'returned_amount', 'document_mime', 'document_sha256', 'document_base64'];

    protected $hidden = ['document_base64'];

    protected function casts(): array
    {
        return ['returned_amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('YDPAY return evidence is immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('YDPAY return evidence is immutable.');
        });
    }
}
