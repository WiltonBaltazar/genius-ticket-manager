<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Order;
use App\Models\Staff;

class OrderPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->hasOrdersAccess($staff);
    }

    public function view(Staff $staff, Order $order): bool
    {
        return $this->hasOrdersAccess($staff);
    }

    public function create(Staff $staff): bool
    {
        // No order fields are ever created or edited from this admin panel
        // (spec.md FR-016) — payment/refund state changes are a separate,
        // out-of-scope workflow.
        return false;
    }

    public function update(Staff $staff, Order $order): bool
    {
        return false;
    }

    public function delete(Staff $staff, Order $order): bool
    {
        return false;
    }

    private function hasOrdersAccess(Staff $staff): bool
    {
        return in_array($staff->role, [StaffRole::SuperAdmin, StaffRole::EventManager, StaffRole::Support], true);
    }
}
