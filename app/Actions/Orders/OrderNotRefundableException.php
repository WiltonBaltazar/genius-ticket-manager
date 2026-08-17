<?php

namespace App\Actions\Orders;

use RuntimeException;

/**
 * Thrown by RefundOrderAction when the order isn't paid — only a paid order
 * has actually collected money to refund; pending, failed, cancelled, expired,
 * and already-refunded orders can never be refunded (again).
 */
class OrderNotRefundableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Only a paid order can be refunded.');
    }
}
