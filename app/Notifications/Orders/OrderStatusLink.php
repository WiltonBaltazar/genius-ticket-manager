<?php

namespace App\Notifications\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued via the database queue driver, supervised alongside php-fpm/nginx
 * in the Docker image (docker/supervisord.conf) — research.md §3's original
 * "no confirmed persistent queue worker" constraint no longer holds now that
 * this always-on worker exists, and sending synchronously meant a transient
 * mail failure turned an already-created order into a 500 for the attendee.
 */
class OrderStatusLink extends Notification implements ShouldQueue
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
        $subject = 'Falta concluir o pagamento — '.config('app.name');

        $this->order->loadMissing('orderItems.ticketType');

        // ->view() bypasses Laravel's default markdown mail component entirely in
        // favor of the same branded template OrderConfirmed uses
        // (resources/views/emails/orders/status.blade.php), just with the
        // pending-status copy — one card design across both order emails
        // instead of a branded one and a plain leftover one.
        return (new MailMessage)
            ->subject($subject)
            ->view('emails.orders.status', [
                'subject' => $subject,
                'preheader' => 'Falta concluir o pagamento do seu pedido para receber os seus bilhetes.',
                'status' => 'pending',
                'headline' => 'Falta concluir o pagamento',
                'introLine' => 'Recebemos o seu pedido. Complete o pagamento para receber os seus bilhetes.',
                'ctaLabel' => 'Concluir o pagamento',
                'helperLine' => 'Guarde este e-mail — é assim que volta a aceder ao seu pedido para pagar e descarregar o bilhete.',
                'attendeeName' => $notifiable->name,
                'items' => $this->order->orderItems,
                'totalAmount' => $this->order->total_amount,
                'expiresAt' => $this->order->created_at->copy()->addHours(24),
                'orderUrl' => url('/orders/'.$this->order->id),
                'orderRef' => strtoupper(substr($this->order->id, 0, 8)),
                'logoBase64' => base64_encode(file_get_contents(public_path('images/logo.png'))),
            ]);
    }
}
