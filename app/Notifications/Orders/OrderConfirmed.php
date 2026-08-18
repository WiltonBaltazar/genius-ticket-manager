<?php

namespace App\Notifications\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued, matching OrderStatusLink — see that class for why (a supervised
 * worker now runs continuously in the Docker image, so the original
 * "no confirmed persistent queue worker" constraint no longer applies).
 */
class OrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketCount = $this->order->orderItems->sum('quantity');
        $subject = ($ticketCount === 1 ? 'O seu bilhete está pronto' : 'Os seus bilhetes estão prontos').' — '.config('app.name');

        // ->view() bypasses Laravel's default markdown mail component entirely in
        // favor of a bespoke branded template (resources/views/emails/orders/status.blade.php,
        // shared with OrderStatusLink) that mirrors the attendee site's own
        // order-status card — ->line()/->action() would render through the
        // generic notifications::email theme instead.
        return (new MailMessage)
            ->subject($subject)
            ->view('emails.orders.status', [
                'subject' => $subject,
                'preheader' => 'O pagamento foi confirmado — os seus bilhetes já estão disponíveis para descarregar.',
                'status' => 'paid',
                'headline' => $ticketCount === 1 ? 'O seu bilhete está pronto' : 'Os seus bilhetes estão prontos',
                'introLine' => 'Pagamento confirmado — os seus bilhetes estão prontos abaixo.',
                'ctaLabel' => 'Ver e descarregar os bilhetes',
                'helperLine' => 'Guarde este e-mail — é assim que volta a aceder aos seus bilhetes.',
                'attendeeName' => $notifiable->name,
                'items' => $this->order->orderItems,
                'totalAmount' => $this->order->total_amount,
                'expiresAt' => null,
                'orderUrl' => url('/orders/'.$this->order->id),
                'orderRef' => strtoupper(substr($this->order->id, 0, 8)),
                // Embedded as a data URI, not asset()/url() — an emailed <img src>
                // pointing at APP_URL (often http://localhost in dev, and blocked by
                // some mail clients' remote-image policies even in production) shows
                // as a broken image for every recipient. Matches TicketPdfController's
                // approach to the same logo for the same reason.
                'logoBase64' => base64_encode(file_get_contents(public_path('images/logo.png'))),
            ]);
    }
}
