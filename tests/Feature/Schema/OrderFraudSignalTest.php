<?php

use App\Models\Order;

it('persists and retrieves ip_address and user_agent on an order', function () {
    $order = Order::factory()->create([
        'ip_address' => '203.0.113.42',
        'user_agent' => 'Mozilla/5.0 (Test Runner)',
    ]);

    $fresh = $order->fresh();

    expect($fresh->ip_address)->toBe('203.0.113.42')
        ->and($fresh->user_agent)->toBe('Mozilla/5.0 (Test Runner)');
});
