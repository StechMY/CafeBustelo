<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DashboardSlide;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardSlidePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DashboardSlide');
    }

    public function view(AuthUser $authUser, DashboardSlide $dashboardSlide): bool
    {
        return $authUser->can('View:DashboardSlide');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DashboardSlide');
    }

    public function update(AuthUser $authUser, DashboardSlide $dashboardSlide): bool
    {
        return $authUser->can('Update:DashboardSlide');
    }

    public function delete(AuthUser $authUser, DashboardSlide $dashboardSlide): bool
    {
        return $authUser->can('Delete:DashboardSlide');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DashboardSlide');
    }

    public function restore(AuthUser $authUser, DashboardSlide $dashboardSlide): bool
    {
        return $authUser->can('Restore:DashboardSlide');
    }

    public function forceDelete(AuthUser $authUser, DashboardSlide $dashboardSlide): bool
    {
        return $authUser->can('ForceDelete:DashboardSlide');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DashboardSlide');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DashboardSlide');
    }

    public function replicate(AuthUser $authUser, DashboardSlide $dashboardSlide): bool
    {
        return $authUser->can('Replicate:DashboardSlide');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DashboardSlide');
    }

}