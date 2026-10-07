<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\PageImage;

class PageImagePolicy
{
    public function before(AdminUser $admin): ?bool
    {
        return $admin->hasRole('super_admin') ? true : null;
    }

    public function viewAny(AdminUser $admin): bool
    {
        return $admin->can('ViewAny:PageImage');
    }

    public function view(AdminUser $admin, PageImage $pageImage): bool
    {
        return $admin->can('View:PageImage');
    }

    public function create(AdminUser $admin): bool
    {
        return $admin->can('Create:PageImage');
    }

    public function update(AdminUser $admin, PageImage $pageImage): bool
    {
        return $admin->can('Update:PageImage');
    }

    public function delete(AdminUser $admin, PageImage $pageImage): bool
    {
        return $admin->can('Delete:PageImage');
    }
}
