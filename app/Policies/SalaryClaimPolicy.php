<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SalaryClaim;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalaryClaimPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SalaryClaim');
    }

    public function view(AuthUser $authUser, SalaryClaim $salaryClaim): bool
    {
        return $authUser->can('View:SalaryClaim');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SalaryClaim');
    }

    public function update(AuthUser $authUser, SalaryClaim $salaryClaim): bool
    {
        return $authUser->can('Update:SalaryClaim');
    }

    public function delete(AuthUser $authUser, SalaryClaim $salaryClaim): bool
    {
        return $authUser->can('Delete:SalaryClaim');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SalaryClaim');
    }

    public function restore(AuthUser $authUser, SalaryClaim $salaryClaim): bool
    {
        return $authUser->can('Restore:SalaryClaim');
    }

    public function forceDelete(AuthUser $authUser, SalaryClaim $salaryClaim): bool
    {
        return $authUser->can('ForceDelete:SalaryClaim');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SalaryClaim');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SalaryClaim');
    }

    public function replicate(AuthUser $authUser, SalaryClaim $salaryClaim): bool
    {
        return $authUser->can('Replicate:SalaryClaim');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SalaryClaim');
    }

}