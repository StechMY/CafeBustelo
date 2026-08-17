<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UserProduct;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserProduct');
    }

    public function view(AuthUser $authUser, UserProduct $userProduct): bool
    {
        return $authUser->can('View:UserProduct');
    }
}
