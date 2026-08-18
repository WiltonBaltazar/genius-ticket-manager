<?php

namespace App\Notifications\Tickets;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the original attendee (the order's own account) confirming a
 * transfer they just made — no CTA, matching every other order/ticket
 * notification's practice of confirming every state change rather than
 * leaving the person who acted to wonder if it worked. Queued, matching
 * every other order/ticket notification — see OrderStatusLink for why.
 */
class TicketTransferConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket, private readonly string $toName, private readonly string $toEmail) {}

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
        $subject = 'Bilhete transferido — '.config('app.name');

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.tickets.transferred', [
                'subject' => $subject,
                'preheader' => "Transferiu um bilhete para {$event->name} a {$this->toName}.",
                'eyebrow' => 'Bilhete transferido',
                'ticketTypeName' => $this->ticket->ticketType->name,
                'toName' => $notifiable->name,
                'introLine' => "Confirmamos a transferência deste bilhete para {$this->toName} ({$this->toEmail}). Já não está disponível para si.",
                'eventName' => $event->name,
                'dateLabel' => $event->ticketDateLabel($this->ticket->event_date),
                'venue' => $event->venue,
                'ctaLabel' => null,
                'ctaUrl' => null,
                'helperLine' => 'Se não foi você quem fez esta transferência, contacte-nos imediatamente.',
            ]);
    }
}
