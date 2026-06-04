<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminUserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdminUser');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:AdminUser');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdminUser');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:AdminUser');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:AdminUser');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AdminUser');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:AdminUser');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:AdminUser');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdminUser');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdminUser');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:AdminUser');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdminUser');
    }

}