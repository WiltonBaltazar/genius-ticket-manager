<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Tickets\TicketNotTransferableException;
use App\Actions\Tickets\TransferTicketAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\TransferTicketRequest;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class TicketTransferController extends Controller
{
    /**
     * No attendee auth: the order UUID + ticket UUID are already the sole
     * "authorization" for every other attendee-facing action on an order
     * (OrderStatusLink, the PDF download) — this follows the same model,
     * same as TicketPdfController's own order/ticket ownership check.
     */
    public function store(TransferTicketRequest $request, Order $order, Ticket $ticket, TransferTicketAction $action): JsonResponse
    {
        abort_if($order->status !== OrderStatus::Paid, 404);
        abort_if($ticket->orderItem->order_id !== $order->id, 404);

        try {
            $ticket = $action->handle(
                $ticket,
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('phone')->toString(),
            );
        } catch (TicketNotTransferableException $e) {
            return response()->json(['error' => $e->reason], 422);
        }

        return response()->json([
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status->value,
                'holder_name' => $ticket->currentHolderName(),
            ],
        ]);
    }
}
