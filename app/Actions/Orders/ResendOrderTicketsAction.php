<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Staff;
use App\Notifications\Orders\OrderConfirmed;
use Illuminate\Support\Facades\Notification;

class ResendOrderTicketsAction
{
    /**
     * Re-sends the same "tickets ready" email ConfirmOrderPaymentAction sends,
     * for an attendee who never got it (spam folder, mail hiccup). Tickets
     * themselves are untouched — they were issued in the same transaction that
     * marked the order paid, and the email only links to the order page.
     *
     * $email sends to a different address instead (e.g. the attendee mistyped
     * theirs at checkout) without changing the attendee's stored email — that
     * email may also be their login. Returns the address it was sent to.
     */
    public function handle(Order $order, Staff $staff, ?string $email = null): string
    {
        if ($order->status !== OrderStatus::Paid) {
            throw new OrderNotPaidException;
        }

        $attendee = $order->attendee;
        $recipient = filled($email) ? $email : $attendee->email;

        $order->auditLogs()->create([
            'staff_id' => $staff->id,
            'action' => 'order.tickets_resent',
            'changes' => ['email' => $recipient],
            'ip_address' => null,
        ]);

        $notification = new OrderConfirmed($order->loadMissing('orderItems.ticketType'));

        if (strcasecmp($recipient, $attendee->email) === 0) {
            $attendee->notify($notification);
        } else {
            Notification::route('mail', $recipient)->notify($notification);
        }

        return $recipient;
    }
}
