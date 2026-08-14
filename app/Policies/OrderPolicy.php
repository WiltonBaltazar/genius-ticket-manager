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
        // (spec.md FR-016). Payment confirmation is a separate, narrow
        // ability (confirmPayment() below, 004-attendee-checkout) — not a
        // general create/edit capability on the resource.
        return false;
    }

    public function update(Staff $staff, Order $order): bool
    {
        return false;
    }

    /**
     * 004-attendee-checkout: the one payment-state-change action this admin
     * panel exposes — pending -> paid, via ConfirmOrderPaymentAction. Reuses
     * the same role set as viewAny/view, per that feature's spec FR-013.
     */
    public function confirmPayment(Staff $staff, Order $order): bool
    {
        return $this->hasOrdersAccess($staff);
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
