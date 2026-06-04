<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DashboardPopup;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardPopupPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DashboardPopup');
    }

    public function view(AuthUser $authUser, DashboardPopup $dashboardPopup): bool
    {
        return $authUser->can('View:DashboardPopup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DashboardPopup');
    }

    public function update(AuthUser $authUser, DashboardPopup $dashboardPopup): bool
    {
        return $authUser->can('Update:DashboardPopup');
    }

    public function delete(AuthUser $authUser, DashboardPopup $dashboardPopup): bool
    {
        return $authUser->can('Delete:DashboardPopup');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DashboardPopup');
    }

    public function restore(AuthUser $authUser, DashboardPopup $dashboardPopup): bool
    {
        return $authUser->can('Restore:DashboardPopup');
    }

    public function forceDelete(AuthUser $authUser, DashboardPopup $dashboardPopup): bool
    {
        return $authUser->can('ForceDelete:DashboardPopup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DashboardPopup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DashboardPopup');
    }

    public function replicate(AuthUser $authUser, DashboardPopup $dashboardPopup): bool
    {
        return $authUser->can('Replicate:DashboardPopup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DashboardPopup');
    }

}