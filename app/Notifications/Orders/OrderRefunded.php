<?php

namespace App\Notifications\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued, matching OrderConfirmed/OrderStatusLink — see OrderStatusLink for
 * why (a supervised worker now runs continuously in the Docker image, so
 * the original "no confirmed persistent queue worker" constraint no longer
 * applies).
 */
class OrderRefunded extends Notification implements ShouldQueue
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
        $subject = 'O seu pedido foi reembolsado — '.config('app.name');

        // ->view() bypasses Laravel's default markdown mail component in favor of
        // the same branded template OrderConfirmed/OrderStatusLink use
        // (resources/views/emails/orders/status.blade.php) — no ctaLabel/orderUrl
        // action here, since a refunded order (tickets voided, money back) has
        // nothing left for the attendee to do.
        return (new MailMessage)
            ->subject($subject)
            ->view('emails.orders.status', [
                'subject' => $subject,
                'preheader' => 'O pagamento do seu pedido foi reembolsado.',
                'status' => 'refunded',
                'headline' => 'O seu pedido foi reembolsado',
                'introLine' => 'O pagamento deste pedido foi reembolsado. Os bilhetes já não são válidos para entrada.',
                'totalLabel' => 'Total reembolsado',
                'ctaLabel' => null,
                'orderUrl' => null,
                'helperLine' => 'Guarde este e-mail como comprovativo do reembolso.',
                'attendeeName' => $notifiable->name,
                'items' => $this->order->orderItems,
                'totalAmount' => $this->order->total_amount,
                'expiresAt' => null,
                'orderRef' => strtoupper(substr($this->order->id, 0, 8)),
            ]);
    }
}
