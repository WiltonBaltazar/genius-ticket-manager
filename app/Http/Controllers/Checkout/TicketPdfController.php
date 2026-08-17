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
            'ticketTypeNameFontSize' => $this->fittedFontSize($ticketType->name, [16 => 114, 24 => 84, 34 => 64], 48),
            'eventNameFontSize' => $this->fittedFontSize($event->name, [28 => 52, 44 => 40], 32),
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

    /**
     * The ticket type name and event name are free text an admin can make arbitrarily
     * long, but they render in a large display face inside a fixed-height single-page
     * PDF canvas — DomPDF has no equivalent of a browser's content-aware CSS (no
     * clamp(), no JS to measure and shrink text), so an uncapped font-size lets a long
     * name wrap to 3+ lines and push the rest of the ticket onto a spilled second page.
     * $tiers maps "up to this many characters" => font-size in px, checked in order;
     * $floor is the size used once the name exceeds every tier.
     *
     * @param  array<int, int>  $tiers
     */
    private function fittedFontSize(string $text, array $tiers, int $floor): int
    {
        $length = mb_strlen($text);

        foreach ($tiers as $maxChars => $fontSize) {
            if ($length <= $maxChars) {
                return $fontSize;
            }
        }

        return $floor;
    }
}
