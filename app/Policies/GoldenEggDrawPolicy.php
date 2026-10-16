<?php

namespace App\Policies;

use App\Models\GoldenEggDraw;
use App\Support\FilamentSupportScope;
use Illuminate\Foundation\Auth\User as AuthUser;

class GoldenEggDrawPolicy
{
    public function viewAny(AuthUser $admin): bool
    {
        return $admin->can('ViewAny:GoldenEggDraw');
    }

    public function view(AuthUser $admin, GoldenEggDraw $draw): bool
    {
        return $admin->can('View:GoldenEggDraw')
            && FilamentSupportScope::canAccessUserId($draw->user_id);
    }
}
