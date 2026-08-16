<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Staff;

class TicketPolicy
{
    /**
     * Door check-in: the one ability gate_operator has anywhere in this app,
     * plus the two management roles who might staff or spot-check the door.
     * support is deliberately excluded — its access elsewhere is limited to
     * order viewing/troubleshooting, not gate operations.
     */
    public function checkIn(Staff $staff): bool
    {
        return in_array($staff->role, [StaffRole::SuperAdmin, StaffRole::EventManager, StaffRole::GateOperator], true);
    }
}
