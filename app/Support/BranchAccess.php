<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Staff-to-branch access rule shared by channel authorization.
 */
class BranchAccess
{
    /**
     * Staff may only access their own branch; admins may access every branch.
     */
    public static function belongsToBranch(UserRole $role, ?int $userBranchId, int $branchId): bool
    {
        return $role === UserRole::Admin || $userBranchId === $branchId;
    }
}
