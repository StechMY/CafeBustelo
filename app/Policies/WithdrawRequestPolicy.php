<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WithdrawRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WithdrawRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WithdrawRequest');
    }

    public function view(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('View:WithdrawRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WithdrawRequest');
    }

    public function update(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('Update:WithdrawRequest');
    }

    public function delete(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('Delete:WithdrawRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WithdrawRequest');
    }

    public function restore(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('Restore:WithdrawRequest');
    }

    public function forceDelete(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('ForceDelete:WithdrawRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WithdrawRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WithdrawRequest');
    }

    public function replicate(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('Replicate:WithdrawRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WithdrawRequest');
    }
}
