<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Unused = 'unused';
    case CheckedIn = 'checked_in';
    case Voided = 'voided';
}
