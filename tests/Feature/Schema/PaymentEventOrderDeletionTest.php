<?php

use App\Models\Order;
use App\Models\PaymentEvent;

it('sets payment_events.order_id to null (not deleted) when the order is force-deleted', function () {
    $order = Order::factory()->create();
    $paymentEvent = PaymentEvent::factory()->for($order)->create();

    $order->forceDelete();

    expect(PaymentEvent::find($paymentEvent->id))->not->toBeNull()
        ->and($paymentEvent->fresh()->order_id)->toBeNull();
});
