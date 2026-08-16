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
            size: 620,
            margin: 24,
        ))->build();

        return Pdf::loadView('tickets.pdf', [
            'order' => $order,
            'event' => $event,
            'ticketType' => $ticketType,
            'ticket' => $ticket,
            'attendeeName' => $attendeeName,
            'qrCodeBase64' => base64_encode($qrCode->getString()),
            'logoBase64' => base64_encode(file_get_contents(public_path('images/logo.png'))),
            'fonts' => $this->fontsBase64(),
        ])->download("ticket-{$ticket->id}.pdf");
    }

    /**
     * The ticket PDF is a fixed 1080×1920 canvas rendered standalone per request (no browser,
     * no CDN reachable at generation time), so brand fonts are vendored under resources/fonts
     * and inlined as data URIs rather than loaded from Bunny Fonts like the web app does.
     *
     * @return array<string, string>
     */
    private function fontsBase64(): array
    {
        $dir = resource_path('fonts/tickets');

        return collect([
            'displayBold' => 'PlayfairDisplay-Bold.ttf',
            'sansRegular' => 'Barlow-Regular.ttf',
            'sansSemibold' => 'Barlow-SemiBold.ttf',
            'condensedSemibold' => 'BarlowCondensed-SemiBold.ttf',
            'condensedBold' => 'BarlowCondensed-Bold.ttf',
        ])->map(fn ($file) => base64_encode(file_get_contents("{$dir}/{$file}")))->all();
    }
}
