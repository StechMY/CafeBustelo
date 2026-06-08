<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SvipPackage;
use Illuminate\Auth\Access\HandlesAuthorization;

class SvipPackagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SvipPackage');
    }

    public function view(AuthUser $authUser, SvipPackage $svipPackage): bool
    {
        return $authUser->can('View:SvipPackage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SvipPackage');
    }

    public function update(AuthUser $authUser, SvipPackage $svipPackage): bool
    {
        return $authUser->can('Update:SvipPackage');
    }

    public function delete(AuthUser $authUser, SvipPackage $svipPackage): bool
    {
        return $authUser->can('Delete:SvipPackage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SvipPackage');
    }

    public function restore(AuthUser $authUser, SvipPackage $svipPackage): bool
    {
        return $authUser->can('Restore:SvipPackage');
    }

    public function forceDelete(AuthUser $authUser, SvipPackage $svipPackage): bool
    {
        return $authUser->can('ForceDelete:SvipPackage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SvipPackage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SvipPackage');
    }

    public function replicate(AuthUser $authUser, SvipPackage $svipPackage): bool
    {
        return $authUser->can('Replicate:SvipPackage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SvipPackage');
    }

}