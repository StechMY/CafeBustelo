<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SalaryClaimWindow;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalaryClaimWindowPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SalaryClaimWindow');
    }

    public function view(AuthUser $authUser, SalaryClaimWindow $salaryClaimWindow): bool
    {
        return $authUser->can('View:SalaryClaimWindow');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SalaryClaimWindow');
    }

    public function update(AuthUser $authUser, SalaryClaimWindow $salaryClaimWindow): bool
    {
        return $authUser->can('Update:SalaryClaimWindow');
    }

    public function delete(AuthUser $authUser, SalaryClaimWindow $salaryClaimWindow): bool
    {
        return $authUser->can('Delete:SalaryClaimWindow');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SalaryClaimWindow');
    }

    public function restore(AuthUser $authUser, SalaryClaimWindow $salaryClaimWindow): bool
    {
        return $authUser->can('Restore:SalaryClaimWindow');
    }

    public function forceDelete(AuthUser $authUser, SalaryClaimWindow $salaryClaimWindow): bool
    {
        return $authUser->can('ForceDelete:SalaryClaimWindow');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SalaryClaimWindow');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SalaryClaimWindow');
    }

    public function replicate(AuthUser $authUser, SalaryClaimWindow $salaryClaimWindow): bool
    {
        return $authUser->can('Replicate:SalaryClaimWindow');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SalaryClaimWindow');
    }

}