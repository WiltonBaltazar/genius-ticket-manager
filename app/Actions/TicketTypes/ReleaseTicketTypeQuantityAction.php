<?php

namespace App\Actions\TicketTypes;

use App\Models\TicketType;

class ReleaseTicketTypeQuantityAction
{
    /**
     * Retries the conditional increment against a fresh read if another
     * write raced this ticket type's version between read and update — a
     * release must actually happen, unlike the checkout decrement
     * (research.md §2), where "someone else already has it" is a legitimate,
     * final outcome rather than something to retry past. Shared by
     * ExpirePendingOrdersAction and RefundOrderAction — both hand a seat
     * back to the sellable pool, just from a different order state.
     */
    public function handle(string $ticketTypeId, int $quantity): void
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
