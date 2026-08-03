<?php

use App\Models\Event;
use App\Models\TicketType;

it('allows an event to have more than one ticket type with independent pricing and availability', function () {
    $event = Event::factory()->create();

    $vip = TicketType::factory()->for($event)->create(['name' => 'VIP', 'price' => 250, 'total_quantity' => 10, 'available_quantity' => 10]);
    $general = TicketType::factory()->for($event)->create(['name' => 'General', 'price' => 50, 'total_quantity' => 200, 'available_quantity' => 200]);

    expect($event->ticketTypes()->count())->toBe(2);

    $general->update(['available_quantity' => 199]);

    expect($vip->fresh()->available_quantity)->toBe(10)
        ->and($general->fresh()->available_quantity)->toBe(199)
        ->and($vip->fresh()->price)->not->toBe($general->fresh()->price);
});
