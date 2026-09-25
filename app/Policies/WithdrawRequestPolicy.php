<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WithdrawRequest;
use App\Support\FilamentSupportScope;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WithdrawRequestPolicy
{
    use HandlesAuthorization;

    public function audit(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $user->can('Audit:Withdraw')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
    }

    public function approveManual(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $user->can('ApproveManual:Withdrawal')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
    }

    public function approveScheduledGateway(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $user->can('ApproveScheduledGateway:Withdrawal')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
    }

    public function revertManualApproval(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $this->approveManual($user, $withdrawRequest);
    }

    public function approveInstantGateway(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $user->can('ApproveInstantGateway:Withdrawal')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
    }

    public function retryGateway(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $user->can('RetryGateway:Withdrawal')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
    }

    public function cancelGateway(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $user->can('CancelGateway:Withdrawal')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
    }

    public function syncGateway(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $user->can('SyncGateway:Withdrawal')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
    }

    public function confirmGatewayUnpaid(AuthUser $user, WithdrawRequest $withdrawRequest): bool
    {
        return $this->cancelGateway($user, $withdrawRequest);
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WithdrawRequest');
    }

    public function view(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('View:WithdrawRequest')
            && FilamentSupportScope::canAccessUserId($withdrawRequest->user_id);
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
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return $authUser->can('Restore:WithdrawRequest');
    }

    public function forceDelete(AuthUser $authUser, WithdrawRequest $withdrawRequest): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
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
