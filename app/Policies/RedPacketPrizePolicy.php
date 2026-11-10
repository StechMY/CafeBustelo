<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\RedPacketPrize;

class RedPacketPrizePolicy
{
    public function before(AdminUser $admin): ?bool
    {
        return $admin->hasRole('super_admin') ? true : null;
    }

    public function viewAny(AdminUser $admin): bool
    {
        return $admin->can('ViewAny:RedPacketPrize');
    }

    public function view(AdminUser $admin, RedPacketPrize $prize): bool
    {
        return $admin->can('View:RedPacketPrize');
    }

    public function update(AdminUser $admin, RedPacketPrize $prize): bool
    {
        return $admin->can('Update:RedPacketPrize');
    }

    public function create(AdminUser $admin): bool
    {
        return false;
    }

    public function delete(AdminUser $admin, RedPacketPrize $prize): bool
    {
        return false;
    }
}
