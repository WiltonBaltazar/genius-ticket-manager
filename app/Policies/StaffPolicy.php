<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Staff;

/**
 * Staff accounts (including who holds super_admin) are the most sensitive
 * thing in the admin panel — restricted to super_admin only, matching
 * PaymentSettings::canAccess()'s same-role gate for other admin-only config.
 */
class StaffPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->role === StaffRole::SuperAdmin;
    }

    public function view(Staff $staff, Staff $model): bool
    {
        return $staff->role === StaffRole::SuperAdmin;
    }

    public function create(Staff $staff): bool
    {
        return $staff->role === StaffRole::SuperAdmin;
    }

    public function update(Staff $staff, Staff $model): bool
    {
        return $staff->role === StaffRole::SuperAdmin;
    }

    /**
     * A super_admin may not delete their own account — the one lockout this
     * policy alone can't recover from (no super_admin left to undo it).
     */
    public function delete(Staff $staff, Staff $model): bool
    {
        return $staff->role === StaffRole::SuperAdmin && $staff->id !== $model->id;
    }
}
