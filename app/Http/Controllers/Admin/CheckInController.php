<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CheckIn\ConfirmTicketCheckInAction;
use App\Actions\CheckIn\TicketCheckInRejectedException;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The door check-in page: a dedicated, lightweight page (not the attendee
 * SPA, not the Filament admin panel) for staff scanning tickets on a phone.
 * Reuses the 'staff' guard session Filament's login already establishes —
 * same auth-gating pattern as OrderProofOfPaymentController.
 */
class CheckInController extends Controller
{
    public function show(): View
    {
        abort_unless(auth('staff')->user()?->can('checkIn', Ticket::class), 403);

        return view('checkin');
    }

    /**
     * Read-only: resolves either an exact qr_code scan or a free-text search
     * (attendee name/email/phone, or an order's short reference) to candidate
     * tickets, without changing anything — confirm() is the only endpoint
     * that mutates a ticket's status.
     */
    public function lookup(Request $request): JsonResponse
    {
        abort_unless(auth('staff')->user()?->can('checkIn', Ticket::class), 403);

        $qrCode = $request->string('qr_code')->toString();
        $query = trim($request->string('q')->toString());

        $tickets = Ticket::query()->with(['ticketType.event', 'orderItem.order.attendee', 'checkedInBy']);

        if ($qrCode !== '') {
            $tickets = $tickets->where('qr_code', $qrCode)->get();
        } elseif ($query !== '') {
            $tickets = $tickets
                ->whereHas('orderItem.order.attendee', function ($attendee) use ($query) {
                    $attendee->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->orWhereHas('orderItem.order', function ($order) use ($query) {
                    $order->where('id', 'like', "{$query}%");
                })
                // A transferred ticket's holder no longer matches the order's own
                // attendee — search that too, or door staff can't find it by the
                // new holder's name once it's been handed over.
                ->orWhere('holder_name', 'like', "%{$query}%")
                ->orWhere('holder_email', 'like', "%{$query}%")
                ->orWhere('holder_phone', 'like', "%{$query}%")
                ->limit(20)
                ->get();
        } else {
            $tickets = collect();
        }

        return response()->json(['tickets' => $tickets->map($this->formatTicket(...))]);
    }

    public function confirm(Request $request, Ticket $ticket, ConfirmTicketCheckInAction $action): JsonResponse
    {
        abort_unless(auth('staff')->user()?->can('checkIn', Ticket::class), 403);

        try {
            $confirmed = $action->handle($ticket, $request->user('staff'), $request->ip());
        } catch (TicketCheckInRejectedException $e) {
            return response()->json([
                'error' => $e->reason,
                'ticket' => $this->formatTicket(
                    $e->ticket->fresh(['ticketType.event', 'orderItem.order.attendee', 'checkedInBy'])
                ),
            ], 422);
        }

        return response()->json(['ticket' => $this->formatTicket($confirmed)]);
    }

    private function formatTicket(Ticket $ticket): array
    {
        $order = $ticket->orderItem->order;

        return [
            'id' => $ticket->id,
            'status' => $ticket->status->value,
            'attendee_name' => $ticket->currentHolderName(),
            'ticket_type_name' => $ticket->ticketType->name,
            'event_name' => $ticket->ticketType->event->name,
            'event_date' => $ticket->event_date?->toDateString(),
            'wrong_day' => $ticket->event_date !== null && $ticket->event_date->toDateString() !== now()->toDateString(),
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            'checked_in_by' => $ticket->checkedInBy?->name,
            'order_reference' => strtoupper(substr($order->id, 0, 8)),
        ];
    }
}
