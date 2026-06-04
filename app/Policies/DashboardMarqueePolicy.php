<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DashboardMarquee;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardMarqueePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DashboardMarquee');
    }

    public function view(AuthUser $authUser, DashboardMarquee $dashboardMarquee): bool
    {
        return $authUser->can('View:DashboardMarquee');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DashboardMarquee');
    }

    public function update(AuthUser $authUser, DashboardMarquee $dashboardMarquee): bool
    {
        return $authUser->can('Update:DashboardMarquee');
    }

    public function delete(AuthUser $authUser, DashboardMarquee $dashboardMarquee): bool
    {
        return $authUser->can('Delete:DashboardMarquee');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DashboardMarquee');
    }

    public function restore(AuthUser $authUser, DashboardMarquee $dashboardMarquee): bool
    {
        return $authUser->can('Restore:DashboardMarquee');
    }

    public function forceDelete(AuthUser $authUser, DashboardMarquee $dashboardMarquee): bool
    {
        return $authUser->can('ForceDelete:DashboardMarquee');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DashboardMarquee');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DashboardMarquee');
    }

    public function replicate(AuthUser $authUser, DashboardMarquee $dashboardMarquee): bool
    {
        return $authUser->can('Replicate:DashboardMarquee');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DashboardMarquee');
    }

}