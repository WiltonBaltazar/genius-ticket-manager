<?php

namespace App\Actions\Checkout;

use App\Models\Order;

/**
 * @param  array<string, int>  $shortfalls  ticket_type_id => quantity actually available, only present when $order is null
 */
readonly class SubmitOrderResult
{
    public function __construct(
        public ?Order $order,
        public array $shortfalls = [],
        public bool $wasAlreadySubmitted = false,
    ) {}
}
