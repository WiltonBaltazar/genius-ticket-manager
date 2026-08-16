<?php

namespace App\Actions\CheckIn;

use App\Enums\TicketStatus;
use App\Models\Staff;
use App\Models\Ticket;

class ConfirmTicketCheckInAction
{
    /**
     * unused -> checked_in: records who/when, same shape as
     * ConfirmOrderPaymentAction's status-transition + AuditLog pattern.
     * A day-pass ticket (event_date set) is only confirmable on its own day —
     * checked against "today" in the app's configured timezone, the same
     * basis SubmitOrderRequest uses to validate a day selection at purchase.
     *
     * @throws TicketCheckInRejectedException
     */
    public function handle(Ticket $ticket, Staff $staff, ?string $ipAddress = null): Ticket
    {
        if ($ticket->status === TicketStatus::Voided) {
            throw new TicketCheckInRejectedException('voided', $ticket);
        }

        if ($ticket->status === TicketStatus::CheckedIn) {
            throw new TicketCheckInRejectedException('already_checked_in', $ticket);
        }

        if ($ticket->event_date && ! $ticket->event_date->isSameDay(now())) {
            throw new TicketCheckInRejectedException('wrong_day', $ticket);
        }

        $ticket->update([
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
        ]);

        $ticket->auditLogs()->create([
            'staff_id' => $staff->id,
            'action' => 'ticket.checked_in',
            'changes' => ['status' => TicketStatus::CheckedIn->value],
            'ip_address' => $ipAddress,
        ]);

        return $ticket->fresh(['ticketType.event', 'orderItem.order.attendee', 'checkedInBy']);
    }
}
