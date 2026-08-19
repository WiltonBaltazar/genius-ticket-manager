<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Staff;
use App\Models\TicketType;

class TicketTypePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->hasTicketTypesAccess($staff);
    }

    public function view(Staff $staff, TicketType $ticketType): bool
    {
        return $this->hasTicketTypesAccess($staff);
    }

    public function create(Staff $staff): bool
    {
        return $this->hasTicketTypesAccess($staff);
    }

    public function update(Staff $staff, TicketType $ticketType): bool
    {
        return $this->hasTicketTypesAccess($staff);
    }

    public function delete(Staff $staff, TicketType $ticketType): bool
    {
        return $staff->role === StaffRole::SuperAdmin;
    }

    private function hasTicketTypesAccess(Staff $staff): bool
    {
        return in_array($staff->role, [StaffRole::SuperAdmin, StaffRole::EventManager], true);
    }
}
