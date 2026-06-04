<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BonusCode;
use Illuminate\Auth\Access\HandlesAuthorization;

class BonusCodePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BonusCode');
    }

    public function view(AuthUser $authUser, BonusCode $bonusCode): bool
    {
        return $authUser->can('View:BonusCode');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BonusCode');
    }

    public function update(AuthUser $authUser, BonusCode $bonusCode): bool
    {
        return $authUser->can('Update:BonusCode');
    }

    public function delete(AuthUser $authUser, BonusCode $bonusCode): bool
    {
        return $authUser->can('Delete:BonusCode');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BonusCode');
    }

    public function restore(AuthUser $authUser, BonusCode $bonusCode): bool
    {
        return $authUser->can('Restore:BonusCode');
    }

    public function forceDelete(AuthUser $authUser, BonusCode $bonusCode): bool
    {
        return $authUser->can('ForceDelete:BonusCode');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BonusCode');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BonusCode');
    }

    public function replicate(AuthUser $authUser, BonusCode $bonusCode): bool
    {
        return $authUser->can('Replicate:BonusCode');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BonusCode');
    }

}