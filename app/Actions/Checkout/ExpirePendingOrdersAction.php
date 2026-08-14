<?php

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

class ExpirePendingOrdersAction
{
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
                    $this->releaseQuantity($item->ticket_type_id, $item->quantity);
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

    /**
     * Retries the conditional increment against a fresh read if another
     * write raced this ticket type's version between read and update — an
     * inventory release must actually happen, unlike the checkout decrement
     * (research.md §2), where "someone else already has it" is a legitimate,
     * final outcome rather than something to retry past.
     */
    private function releaseQuantity(string $ticketTypeId, int $quantity): void
    {
        $attempts = 0;

        do {
            $ticketType = TicketType::where('id', $ticketTypeId)->first();

            $affected = TicketType::where('id', $ticketTypeId)
                ->where('version', $ticketType->version)
                ->update([
                    'available_quantity' => $ticketType->available_quantity + $quantity,
                    'version' => $ticketType->version + 1,
                ]);

            $attempts++;
        } while ($affected === 0 && $attempts < 5);
    }
}
