<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_TELEGRAM = 'telegram';
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_LINK = 'link';
    public const TYPE_ZALO = 'zalo';

    protected $fillable = [
        'name',
        'type',
        'link',
        'status',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function displayIcon(): string
    {
        return match ($this->type) {
            self::TYPE_WHATSAPP => 'whatsapp',
            self::TYPE_TELEGRAM => 'telegram',
            self::TYPE_EMAIL => 'email',
            self::TYPE_PHONE => 'phone',
            self::TYPE_ZALO => 'zalo',
            default => 'link',
        };
    }
}