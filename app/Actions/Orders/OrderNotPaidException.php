<?php

namespace App\Actions\Orders;

use RuntimeException;

/**
 * Thrown by ResendOrderTicketsAction when the order isn't paid — a pending
 * order has no tickets yet, and a refunded one's tickets are all voided.
 */
class OrderNotPaidException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Only a paid order has tickets to resend.');
    }
}
