<?php

namespace App\Actions\Checkout;

use App\Actions\TicketTypes\ReleaseTicketTypeQuantityAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ExpirePendingOrdersAction
{
    public function __construct(private readonly ReleaseTicketTypeQuantityAction $releaseTicketTypeQuantity) {}

    /**
     * Finds pending orders older than 24h, releases each line item's reserved
     * quantity back to available_quantity, and marks the order expired
     * (research.md §3, spec.md FR-017). Returns how many orders were expired.
     */
    public function handle(): int
    {
        $staleOrders = Order::where('status', OrderStatus::Pending)
            ->where('created_at', '<=', now()->subHours(24))
            ->with('orderItems')
            ->get();

        foreach ($staleOrders as $order) {
            DB::transaction(function () use ($order) {
                foreach ($order->orderItems as $item) {
                    $this->releaseTicketTypeQuantity->handle($item->ticket_type_id, $item->quantity);
                }

                $order->update(['status' => OrderStatus::Expired]);

                $order->auditLogs()->create([
                    'staff_id' => null,
                    'action' => 'order.expired',
                    'changes' => ['status' => OrderStatus::Expired->value],
                    'ip_address' => null,
                ]);
            });
        }

        return $staleOrders->count();
    }
}
