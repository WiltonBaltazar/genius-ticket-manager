<?php

namespace App\Actions\Orders;

use App\Actions\TicketTypes\ReleaseTicketTypeQuantityAction;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Staff;
use App\Notifications\Orders\OrderRefunded;
use Illuminate\Support\Facades\DB;

class RefundOrderAction
{
    public function __construct(private readonly ReleaseTicketTypeQuantityAction $releaseTicketTypeQuantity) {}

    /**
     * paid -> refunded: voids every ticket issued on the order — a voided
     * ticket is already rejected at the door by ConfirmTicketCheckInAction, so
     * this alone stops a refunded ticket getting back in — releases each line
     * item's quantity back to available_quantity (mirroring
     * ExpirePendingOrdersAction: a refunded seat goes back on sale rather than
     * staying phantom-sold), and records who/when refunded.
     */
    public function handle(Order $order, Staff $staff): Order
    {
        if ($order->status !== OrderStatus::Paid) {
            throw new OrderNotRefundableException;
        }

        DB::transaction(function () use ($order, $staff) {
            $order->tickets()->update(['status' => TicketStatus::Voided]);

            foreach ($order->orderItems as $item) {
                $this->releaseTicketTypeQuantity->handle($item->ticket_type_id, $item->quantity);
            }

            $order->update([
                'status' => OrderStatus::Refunded,
                'refunded_by' => $staff->id,
                'refunded_at' => now(),
            ]);

            $order->auditLogs()->create([
                'staff_id' => $staff->id,
                'action' => 'order.refunded',
                'changes' => ['status' => OrderStatus::Refunded->value],
                'ip_address' => null,
            ]);
        });

        $order = $order->fresh(['tickets', 'orderItems.ticketType', 'attendee']);

        // Sent after the transaction commits, not inside it — a synchronous mail
        // send is slow relative to a DB write and shouldn't hold the row lock.
        $order->attendee->notify(new OrderRefunded($order));

        return $order;
    }
}
