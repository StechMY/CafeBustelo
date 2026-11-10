<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\RedPacketDraw;
use App\Support\FilamentSupportScope;

class RedPacketDrawPolicy
{
    public function viewAny(AdminUser $admin): bool
    {
        return $admin->isSuperAdmin() || $admin->can('ViewAny:RedPacketDraw');
    }

    public function view(AdminUser $admin, RedPacketDraw $draw): bool
    {
        return $admin->isSuperAdmin()
            || ($admin->can('View:RedPacketDraw') && FilamentSupportScope::canAccessUserId($draw->user_id));
    }

    public function create(AdminUser $admin): bool { return false; }
    public function update(AdminUser $admin, RedPacketDraw $draw): bool { return false; }
    public function delete(AdminUser $admin, RedPacketDraw $draw): bool { return false; }
}
