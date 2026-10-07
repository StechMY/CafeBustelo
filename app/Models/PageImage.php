<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PageImage extends Model
{
    protected $fillable = [
        'page',
        'title',
        'image_path',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public static function pageOptions(): array
    {
        return [
            'dashboard' => __('navigation.dashboard'),
            'my-team' => __('my_team.my_team'),
            'products' => __('navigation.products'),
            'profile' => __('navigation.profile'),
        ];
    }

    public function getImageUrlAttribute(): string
    {
        return Storage::disk('do_spaces')->url($this->image_path);
    }

    public function scopeVisibleForPage(Builder $query, string $page): Builder
    {
        return $query
            ->where('page', $page)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
