<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Notifications\Tickets\TicketTransferConfirmed;
use App\Notifications\Tickets\TicketTransferred;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TransferTicketAction
{
    /**
     * Reassigns an unused ticket to a new name/email — self-service, no
     * account needed for the new holder (design choice): the order's UUID +
     * ticket UUID are already the sole "authorization" for every other
     * attendee-facing action on an order (OrderStatusLink, the PDF download),
     * so this follows the same model rather than introducing attendee login.
     * A checked-in or voided ticket can never be transferred — there's
     * nothing left to hand over.
     *
     * @throws TicketNotTransferableException
     */
    public function handle(Ticket $ticket, string $name, string $email, string $phone): Ticket
    {
        if ($ticket->status === TicketStatus::Voided) {
            throw new TicketNotTransferableException('voided', $ticket);
        }

        if ($ticket->status === TicketStatus::CheckedIn) {
            throw new TicketNotTransferableException('already_checked_in', $ticket);
        }

        $fromName = $ticket->currentHolderName();

        DB::transaction(function () use ($ticket, $name, $email, $phone) {
            $ticket->update([
                'holder_name' => $name,
                'holder_email' => $email,
                'holder_phone' => $phone,
                'transferred_at' => now(),
            ]);

            $ticket->auditLogs()->create([
                'staff_id' => null,
                'action' => 'ticket.transferred',
                'changes' => ['holder_name' => $name, 'holder_email' => $email, 'holder_phone' => $phone],
                'ip_address' => null,
            ]);
        });

        $ticket = $ticket->fresh(['ticketType.event', 'orderItem.order.attendee']);

        // Both sent after the transaction commits, not inside it — a synchronous
        // mail send is slow relative to a DB write and shouldn't hold the row lock.
        Notification::route('mail', $email)->notify(new TicketTransferred($ticket, $name, $fromName));
        $ticket->orderItem->order->attendee->notify(new TicketTransferConfirmed($ticket, $name, $email));

        return $ticket;
    }
}
