<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Collection;

class ExportEventAttendeesAction
{
    /**
     * Header row for the XLSX — kept here, beside the row keys it must match,
     * rather than in ViewEvent where it could silently drift out of order.
     */
    public const COLUMNS = ['Name', 'Email', 'Phone', 'Ticket Type', 'Event Date', 'Status', 'Checked In At', 'Transferred At', 'Buyer Name', 'Buyer Email', 'Order Reference', 'Order Status'];

    /**
     * Every ticket issued for the event, one row per ticket rather than per
     * order — a multi-ticket order can have each ticket transferred to a
     * different person (Ticket::currentHolderName/Email/Phone), so the order's
     * own attendee isn't necessarily who's actually coming. Includes voided
     * and checked-in tickets too (not just "still valid") — Status and Order
     * Status together explain why, rather than silently dropping rows an
     * organizer might still want to see (e.g. who got refunded).
     *
     * @return Collection<int, array<string, string|null>>
     */
    public function handle(Event $event): Collection
    {
        return Ticket::query()
            ->whereHas('ticketType', fn ($query) => $query->where('event_id', $event->id))
            ->with(['ticketType', 'orderItem.order.attendee'])
            ->get()
            // Oldest purchase first; tickets within the same order keep their issue order.
            ->sortBy([
                fn (Ticket $a, Ticket $b) => $a->orderItem->order->created_at <=> $b->orderItem->order->created_at,
                fn (Ticket $a, Ticket $b) => $a->created_at <=> $b->created_at,
                fn (Ticket $a, Ticket $b) => $a->getKey() <=> $b->getKey(),
            ])
            ->values()
            ->map(fn (Ticket $ticket) => [
                'Name' => $ticket->currentHolderName(),
                'Email' => $ticket->currentHolderEmail(),
                'Phone' => $ticket->currentHolderPhone(),
                'Ticket Type' => $ticket->ticketType->name,
                'Event Date' => $ticket->event_date?->toDateString(),
                'Status' => $ticket->status->value,
                'Checked In At' => $ticket->checked_in_at?->toIso8601String(),
                'Transferred At' => $ticket->transferred_at?->toIso8601String(),
                'Buyer Name' => $ticket->orderItem->order->attendee->name,
                'Buyer Email' => $ticket->orderItem->order->attendee->email,
                'Order Reference' => strtoupper(substr($ticket->orderItem->order_id, 0, 8)),
                'Order Status' => $ticket->orderItem->order->status->value,
            ]);
    }
}
