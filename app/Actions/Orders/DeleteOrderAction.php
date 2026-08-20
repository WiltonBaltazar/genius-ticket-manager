<?php

namespace App\Actions\Orders;

use App\Actions\TicketTypes\ReleaseTicketTypeQuantityAction;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class DeleteOrderAction
{
    public function __construct(private readonly ReleaseTicketTypeQuantityAction $releaseTicketTypeQuantity) {}

    /**
     * Soft-deletes the order (OrderPolicy::delete(), super_admin only,
     * unrestricted by status per that product decision). SubmitOrderAction
     * decrements available_quantity up front (40 -> 39 on a 1-ticket order)
     * and nothing gives it back until either ExpirePendingOrdersAction,
     * RefundOrderAction, or this delete releases it — so unless that already
     * happened, deleting releases each line item's quantity too, mirroring
     * those two. A paid order also has real issued tickets, so — same as
     * RefundOrderAction — those are voided first; skipping that step would
     * put the seat back on sale while the original ticket stayed valid at
     * the gate, an oversell. Already-refunded/expired orders are skipped
     * entirely here since they released their quantity (and, for refunded,
     * voided their tickets) at that transition already — doing it again on
     * delete would double-release.
     */
    public function handle(Order $order, Staff $staff): bool
    {
        return DB::transaction(function () use ($order, $staff) {
            if (! in_array($order->status, [OrderStatus::Refunded, OrderStatus::Expired], true)) {
                $order->tickets()->update(['status' => TicketStatus::Voided]);

                foreach ($order->orderItems as $item) {
                    $this->releaseTicketTypeQuantity->handle($item->ticket_type_id, $item->quantity);
                }
            }

            $order->auditLogs()->create([
                'staff_id' => $staff->id,
                'action' => 'order.deleted',
                'changes' => ['status' => $order->status->value],
                'ip_address' => null,
            ]);

            return $order->delete();
        });
    }
}
