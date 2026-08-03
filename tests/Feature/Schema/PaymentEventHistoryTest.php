<?php

use App\Models\Order;
use App\Models\PaymentEvent;

it('preserves every payment event row for the same order rather than overwriting', function () {
    $order = Order::factory()->create();

    PaymentEvent::factory()->for($order)->create(['event_type' => 'authorized']);
    PaymentEvent::factory()->for($order)->create(['event_type' => 'captured']);
    PaymentEvent::factory()->for($order)->create(['event_type' => 'refunded']);

    expect($order->paymentEvents()->count())->toBe(3)
        ->and($order->paymentEvents()->pluck('event_type')->all())
        ->toBe(['authorized', 'captured', 'refunded']);
});
