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
        return (new MailMessage)
            ->subject('Your order — '.config('app.name'))
            ->line('Thanks for your order! Here\'s a link to check its status and complete payment.')
            ->action('View your order', url('/orders/'.$this->order->id))
            ->line('Keep this link — it\'s how you\'ll come back to pay and download your ticket once confirmed.');
    }
}
