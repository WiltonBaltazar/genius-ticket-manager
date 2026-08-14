<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Staff;

class EventPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->hasEventsAccess($staff);
    }

    public function view(Staff $staff, Event $event): bool
    {
        return $this->hasEventsAccess($staff);
    }

    public function create(Staff $staff): bool
    {
        return $this->hasEventsAccess($staff);
    }

    public function update(Staff $staff, Event $event): bool
    {
        return $this->hasEventsAccess($staff);
    }

    public function delete(Staff $staff, Event $event): bool
    {
        return $staff->role === StaffRole::SuperAdmin;
    }

    private function hasEventsAccess(Staff $staff): bool
    {
        return in_array($staff->role, [StaffRole::SuperAdmin, StaffRole::EventManager], true);
    }
}
