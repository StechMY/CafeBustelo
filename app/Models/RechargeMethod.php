<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RechargeMethod extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'bank_name',
        'account_name',
        'account_no',
        'qr_image_path',
        'qr_image_disk',
        'instructions',
        'status',
        'sort_order',
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

    public function getQrImageUrlAttribute(): ?string
    {
        if (! $this->qr_image_path) {
            return null;
        }

        return Storage::disk($this->qr_image_disk ?: 'do_spaces')
            ->url($this->qr_image_path);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => __('filament.active'),
            self::STATUS_INACTIVE => __('filament.inactive'),
        ];
    }
}