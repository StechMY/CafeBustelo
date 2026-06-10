<?php

namespace App\Support\Filament\Dashboard;

use App\Models\AdminUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PlatformDashboardQuery
{
    protected static ?array $supportDownlineUserIds = null;

    protected static bool $supportDownlineUserIdsLoaded = false;

    public static function startDate(array $filters): ?Carbon
    {
        $value = $filters['startDate'] ?? null;

        return filled($value)
            ? Carbon::parse($value)->startOfDay()
            : null;
    }

    public static function endDate(array $filters): ?Carbon
    {
        $value = $filters['endDate'] ?? null;

        return filled($value)
            ? Carbon::parse($value)->endOfDay()
            : null;
    }

    public static function virtualMode(array $filters): string
    {
        return $filters['virtualMode'] ?? 'exclude_virtual';
    }

    public static function applyDateRange(
        Builder $query,
        array $filters,
        string $column = 'created_at',
    ): Builder {
        $startDate = self::startDate($filters);
        $endDate = self::endDate($filters);

        return $query
            ->when($startDate, fn (Builder $query): Builder => $query->where($column, '>=', $startDate))
            ->when($endDate, fn (Builder $query): Builder => $query->where($column, '<=', $endDate));
    }

    public static function admin(): ?AdminUser
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser ? $admin : null;
    }

    public static function isRestrictedSupport(): bool
    {
        $admin = self::admin();

        return $admin?->hasDownlineRestriction() === true;
    }

    /**
     * null = unrestricted admin
     * [] = restricted support but no visible downline
     * [1,2,3] = restricted support visible user ids
     */
    public static function supportDownlineUserIds(): ?array
    {
        if (! self::isRestrictedSupport()) {
            return null;
        }

        if (self::$supportDownlineUserIdsLoaded) {
            return self::$supportDownlineUserIds;
        }

        self::$supportDownlineUserIdsLoaded = true;

        $admin = self::admin();

        self::$supportDownlineUserIds = $admin
            ? $admin->managedDownlineUserIds()
            : [];

        return self::$supportDownlineUserIds;
    }

    public static function applyDirectUserSupportScope(Builder $query): Builder
    {
        $ids = self::supportDownlineUserIds();

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('users.id', $ids);
    }

    public static function applyRelatedUserSupportScope(
        Builder $query,
        string $relation = 'user',
    ): Builder {
        $ids = self::supportDownlineUserIds();

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($relation, function (Builder $query) use ($ids): void {
            $query->whereIn('users.id', $ids);
        });
    }

    public static function applyUserVirtualFilter(
        Builder $query,
        array $filters,
        string $relation = 'user',
    ): Builder {
        self::applyRelatedUserSupportScope($query, $relation);

        $mode = self::virtualMode($filters);

        if ($mode === 'include_all') {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $query) use ($mode): void {
            match ($mode) {
                'only_virtual' => $query->where('is_virtual', true),
                default => $query->where('is_virtual', false),
            };
        });
    }

    public static function applyDirectUserVirtualFilter(
        Builder $query,
        array $filters,
    ): Builder {
        self::applyDirectUserSupportScope($query);

        $mode = self::virtualMode($filters);

        return match ($mode) {
            'include_all' => $query,
            'only_virtual' => $query->where('is_virtual', true),
            default => $query->where('is_virtual', false),
        };
    }

    public static function money(int|float|string|null $amount): string
    {
        return config('app.currency') . ' ' . number_format((float) ($amount ?? 0), 2);
    }

    public static function shortMoney(int|float|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        if (abs($amount) >= 1_000_000) {
            return config('app.currency') . ' ' . number_format($amount / 1_000_000, 2) . 'M';
        }

        if (abs($amount) >= 1_000) {
            return config('app.currency') . ' ' . number_format($amount / 1_000, 2) . 'K';
        }

        return config('app.currency') . ' ' . number_format($amount, 2);
    }
}