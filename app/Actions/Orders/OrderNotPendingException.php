<?php

namespace App\Actions\Orders;

use RuntimeException;

/**
 * Thrown by ConfirmOrderPaymentAction when the order isn't pending
 * (spec.md FR-012) — an already-paid, expired, or otherwise non-pending
 * order can never be confirmed.
 */
class OrderNotPendingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This order is not pending and cannot be confirmed.');
    }
}
