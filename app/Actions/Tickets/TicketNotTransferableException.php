<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use RuntimeException;

/**
 * Thrown by TransferTicketAction when a ticket can't be reassigned — carries
 * a $reason the controller maps to a distinct attendee-facing message, same
 * pattern as TicketCheckInRejectedException. Reuses that action's own
 * 'already_checked_in'/'voided' vocabulary since both describe the same
 * underlying ticket state.
 */
class TicketNotTransferableException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly Ticket $ticket)
    {
        parent::__construct("Ticket transfer rejected: {$reason}.");
    }
}
