<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RechargeRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RechargeRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RechargeRequest');
    }

    public function view(AuthUser $authUser, RechargeRequest $rechargeRequest): bool
    {
        return $authUser->can('View:RechargeRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RechargeRequest');
    }

    public function update(AuthUser $authUser, RechargeRequest $rechargeRequest): bool
    {
        return $authUser->can('Update:RechargeRequest');
    }

    public function delete(AuthUser $authUser, RechargeRequest $rechargeRequest): bool
    {
        return $authUser->can('Delete:RechargeRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RechargeRequest');
    }

    public function restore(AuthUser $authUser, RechargeRequest $rechargeRequest): bool
    {
        return $authUser->can('Restore:RechargeRequest');
    }

    public function forceDelete(AuthUser $authUser, RechargeRequest $rechargeRequest): bool
    {
        return $authUser->can('ForceDelete:RechargeRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RechargeRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RechargeRequest');
    }

    public function replicate(AuthUser $authUser, RechargeRequest $rechargeRequest): bool
    {
        return $authUser->can('Replicate:RechargeRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RechargeRequest');
    }
}
