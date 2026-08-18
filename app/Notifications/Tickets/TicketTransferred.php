<?php

namespace App\Notifications\Tickets;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the new holder on an on-demand mail route (Notification::route),
 * not ->notify() on an Attendee — a transferred-to recipient needs no
 * account (design choice: self-service transfer, no-account recipient).
 * Queued, matching every other order/ticket notification — see
 * OrderStatusLink for why (a supervised worker now runs continuously in the
 * Docker image, so the original "no confirmed persistent queue worker"
 * constraint no longer applies).
 */
class TicketTransferred extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket, private readonly string $toName, private readonly string $fromName) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->ticket->ticketType->event;
        $subject = "Recebeu um bilhete para {$event->name} — ".config('app.name');
        $order = $this->ticket->orderItem->order;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.tickets.transferred', [
                'subject' => $subject,
                'preheader' => "{$this->fromName} transferiu-lhe um bilhete para {$event->name}.",
                'eyebrow' => 'Bilhete de entrada',
                'ticketTypeName' => $this->ticket->ticketType->name,
                'toName' => $this->toName,
                'introLine' => "{$this->fromName} transferiu-lhe um bilhete. Aqui estão os detalhes:",
                'eventName' => $event->name,
                'dateLabel' => $event->ticketDateLabel($this->ticket->event_date),
                'venue' => $event->venue,
                'ctaLabel' => 'Descarregar bilhete',
                'ctaUrl' => url("/orders/{$order->id}/tickets/{$this->ticket->id}/pdf"),
                'helperLine' => 'Guarde este e-mail — é assim que acede ao seu bilhete.',
            ]);
    }
}
