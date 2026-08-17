<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Staff;
use App\Models\Ticket;
use App\Notifications\Orders\OrderConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfirmOrderPaymentAction
{
    /**
     * pending -> paid: records who/when confirmed, and issues one Ticket per
     * unit purchased across the order's items (research.md §4/§7) — tickets
     * are never created before this point, so an expired order never needs
     * ticket cleanup.
     */
    public function handle(Order $order, Staff $staff): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new OrderNotPendingException;
        }

        DB::transaction(function () use ($order, $staff) {
            $order->update([
                'status' => OrderStatus::Paid,
                'confirmed_by' => $staff->id,
                'confirmed_at' => now(),
            ]);

            foreach ($order->orderItems as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::create([
                        'order_item_id' => $item->id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'event_date' => $item->event_date,
                        'qr_code' => Str::random(48),
                        'status' => TicketStatus::Unused,
                    ]);
                }
            }

            $order->auditLogs()->create([
                'staff_id' => $staff->id,
                'action' => 'order.confirmed',
                'changes' => ['status' => OrderStatus::Paid->value],
                'ip_address' => null,
            ]);
        });

        $order = $order->fresh(['tickets', 'orderItems.ticketType', 'attendee']);

        // Sent after the transaction commits, not inside it — a synchronous mail
        // send is slow relative to a DB write and shouldn't hold the row lock.
        $order->attendee->notify(new OrderConfirmed($order));

        return $order;
    }
}
