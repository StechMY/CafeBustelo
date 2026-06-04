<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\VipLevel;
use Illuminate\Auth\Access\HandlesAuthorization;

class VipLevelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VipLevel');
    }

    public function view(AuthUser $authUser, VipLevel $vipLevel): bool
    {
        return $authUser->can('View:VipLevel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VipLevel');
    }

    public function update(AuthUser $authUser, VipLevel $vipLevel): bool
    {
        return $authUser->can('Update:VipLevel');
    }

    public function delete(AuthUser $authUser, VipLevel $vipLevel): bool
    {
        return $authUser->can('Delete:VipLevel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VipLevel');
    }

    public function restore(AuthUser $authUser, VipLevel $vipLevel): bool
    {
        return $authUser->can('Restore:VipLevel');
    }

    public function forceDelete(AuthUser $authUser, VipLevel $vipLevel): bool
    {
        return $authUser->can('ForceDelete:VipLevel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VipLevel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VipLevel');
    }

    public function replicate(AuthUser $authUser, VipLevel $vipLevel): bool
    {
        return $authUser->can('Replicate:VipLevel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VipLevel');
    }

}