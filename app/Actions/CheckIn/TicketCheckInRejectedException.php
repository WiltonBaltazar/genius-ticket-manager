<?php

namespace App\Actions\CheckIn;

use App\Models\Ticket;
use RuntimeException;

/**
 * Thrown by ConfirmTicketCheckInAction when a ticket can't be checked in as
 * scanned — carries a $reason the controller maps to a distinct door-facing
 * message, since "already used", "voided", and "wrong day" all need different
 * feedback for staff (not just a generic failure).
 */
class TicketCheckInRejectedException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly Ticket $ticket)
    {
        parent::__construct("Ticket check-in rejected: {$reason}.");
    }
}
