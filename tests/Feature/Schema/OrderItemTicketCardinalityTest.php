<?php

use App\Models\OrderItem;
use App\Models\Ticket;

it('has exactly as many tickets as its quantity', function () {
    $item = OrderItem::factory()->create(['quantity' => 3]);

    Ticket::factory()->for($item, 'orderItem')->for($item->ticketType, 'ticketType')->count(3)->create();

    expect($item->tickets()->count())->toBe($item->quantity)
        ->and($item->tickets()->count())->toBe(3);
});
