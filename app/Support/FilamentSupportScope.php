<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FilamentSupportScope
{
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

    public static function managedUser(): ?User
    {
        return self::admin()?->managedUser;
    }

    public static function downlineUserIds(?int $maxDepth = null): array
    {
        $admin = self::admin();

        if (!$admin?->hasDownlineRestriction()) {
            return [];
        }

        return $admin->managedDownlineUserIds($maxDepth);
    }

    public static function applyToUsersQuery(Builder $query): Builder
    {
        if (!self::isRestrictedSupport()) {
            return $query;
        }

        $ids = self::downlineUserIds();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('users.id', $ids);
    }

    public static function canAccessUser(User $user): bool
    {
        $admin = self::admin();

        if (!$admin?->hasDownlineRestriction()) {
            return true;
        }

        return in_array((int) $user->id, self::downlineUserIds(), true);
    }

    public static function applyToUserOwnedQuery(
        Builder $query,
        string $userColumn = 'user_id',
    ): Builder {
        if (!self::isRestrictedSupport()) {
            return $query;
        }

        $ids = self::downlineUserIds();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($userColumn, $ids);
    }

    public static function canAccessUserId(int|string|null $userId): bool
    {
        if (!self::isRestrictedSupport()) {
            return true;
        }

        if ($userId === null || $userId === '') {
            return false;
        }

        return in_array((int) $userId, self::downlineUserIds(), true);
    }

    public static function applyToBonusCodeQuery(Builder $query): Builder
    {
        if (!self::isRestrictedSupport()) {
            return $query;
        }

        $ids = self::downlineUserIds();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($ids): void {
            $query
                ->whereIn('owner_user_id', $ids)
                ->orWhereHas('claim', function (Builder $query) use ($ids): void {
                    $query->whereIn('user_id', $ids);
                });
        });
    }

    public static function canAccessBonusCode($bonusCode): bool
    {
        if (!self::isRestrictedSupport()) {
            return true;
        }

        $ids = self::downlineUserIds();

        if ($ids === []) {
            return false;
        }

        if ($bonusCode->owner_user_id && in_array((int) $bonusCode->owner_user_id, $ids, true)) {
            return true;
        }

        $claimUserId = $bonusCode->claim?->user_id;

        return $claimUserId !== null
            && in_array((int) $claimUserId, $ids, true);
    }
}