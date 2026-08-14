<?php

namespace App\Enums;

enum StaffRole: string
{
    case SuperAdmin = 'super_admin';
    case EventManager = 'event_manager';
    case Support = 'support';
    case GateOperator = 'gate_operator';
}
