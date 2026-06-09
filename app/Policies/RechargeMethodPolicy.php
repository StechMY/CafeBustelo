<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RechargeMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class RechargeMethodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RechargeMethod');
    }

    public function view(AuthUser $authUser, RechargeMethod $rechargeMethod): bool
    {
        return $authUser->can('View:RechargeMethod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RechargeMethod');
    }

    public function update(AuthUser $authUser, RechargeMethod $rechargeMethod): bool
    {
        return $authUser->can('Update:RechargeMethod');
    }

    public function delete(AuthUser $authUser, RechargeMethod $rechargeMethod): bool
    {
        return $authUser->can('Delete:RechargeMethod');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RechargeMethod');
    }

    public function restore(AuthUser $authUser, RechargeMethod $rechargeMethod): bool
    {
        return $authUser->can('Restore:RechargeMethod');
    }

    public function forceDelete(AuthUser $authUser, RechargeMethod $rechargeMethod): bool
    {
        return $authUser->can('ForceDelete:RechargeMethod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RechargeMethod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RechargeMethod');
    }

    public function replicate(AuthUser $authUser, RechargeMethod $rechargeMethod): bool
    {
        return $authUser->can('Replicate:RechargeMethod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RechargeMethod');
    }

}