<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CommissionLevel;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommissionLevelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CommissionLevel');
    }

    public function view(AuthUser $authUser, CommissionLevel $commissionLevel): bool
    {
        return $authUser->can('View:CommissionLevel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CommissionLevel');
    }

    public function update(AuthUser $authUser, CommissionLevel $commissionLevel): bool
    {
        return $authUser->can('Update:CommissionLevel');
    }

    public function delete(AuthUser $authUser, CommissionLevel $commissionLevel): bool
    {
        return $authUser->can('Delete:CommissionLevel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CommissionLevel');
    }

    public function restore(AuthUser $authUser, CommissionLevel $commissionLevel): bool
    {
        return $authUser->can('Restore:CommissionLevel');
    }

    public function forceDelete(AuthUser $authUser, CommissionLevel $commissionLevel): bool
    {
        return $authUser->can('ForceDelete:CommissionLevel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CommissionLevel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CommissionLevel');
    }

    public function replicate(AuthUser $authUser, CommissionLevel $commissionLevel): bool
    {
        return $authUser->can('Replicate:CommissionLevel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CommissionLevel');
    }

}