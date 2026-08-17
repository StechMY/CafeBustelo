<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\BankAccountReviewRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class BankAccountReviewRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->isSuperAdmin()
            || $adminUser->can('ViewAny:BankAccountReviewRequest');
    }

    public function view(AdminUser $adminUser, BankAccountReviewRequest $bankAccountReviewRequest): bool
    {
        return $adminUser->isSuperAdmin()
            || $adminUser->can('View:BankAccountReviewRequest');
    }

    public function update(AdminUser $adminUser, BankAccountReviewRequest $bankAccountReviewRequest): bool
    {
        return $adminUser->isSuperAdmin()
            || $adminUser->can('Update:BankAccountReviewRequest');
    }
}
