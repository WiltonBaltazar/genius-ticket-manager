<?php

namespace App\Http\Controllers\Checkout;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\Response;

class TicketPdfController extends Controller
{
    public function show(Order $order, Ticket $ticket): Response
    {
        abort_if($order->status !== OrderStatus::Paid, 404);
        abort_if($ticket->orderItem->order_id !== $order->id, 404);

        $ticketType = $ticket->ticketType;
        $event = $ticketType->event;
        $attendeeName = $order->attendee->name;

        $qrCode = (new Builder(
            writer: new PngWriter,
            data: $ticket->qr_code,
            size: 300,
            margin: 10,
        ))->build();

        return Pdf::loadView('tickets.pdf', [
            'event' => $event,
            'ticketType' => $ticketType,
            'ticket' => $ticket,
            'attendeeName' => $attendeeName,
            'qrCodeBase64' => base64_encode($qrCode->getString()),
        ])->download("ticket-{$ticket->id}.pdf");
    }
}
