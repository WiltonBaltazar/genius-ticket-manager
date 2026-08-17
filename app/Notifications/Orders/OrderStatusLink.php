<?php

namespace App\Notifications\Orders;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately NOT ShouldQueue — a guest attendee's only way back to a
 * pending order is this link (FR-010a), and this project has no confirmed
 * persistent queue worker (research.md §3's Constraints note), so this sends
 * synchronously to guarantee it's actually attempted before the request ends.
 */
class OrderStatusLink extends Notification
{
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
