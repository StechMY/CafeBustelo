<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\GoldenEggPrize;

class GoldenEggPrizePolicy
{
    public function before(AdminUser $admin): ?bool
    {
        return $admin->hasRole('super_admin') ? true : null;
    }

    public function viewAny(AdminUser $admin): bool
    {
        return $admin->can('ViewAny:GoldenEggPrize');
    }

    public function view(AdminUser $admin, GoldenEggPrize $prize): bool
    {
        return $admin->can('View:GoldenEggPrize');
    }

    public function update(AdminUser $admin, GoldenEggPrize $prize): bool
    {
        return $admin->can('Update:GoldenEggPrize');
    }

    public function create(AdminUser $admin): bool
    {
        return false;
    }

    public function delete(AdminUser $admin, GoldenEggPrize $prize): bool
    {
        return false;
    }
}
