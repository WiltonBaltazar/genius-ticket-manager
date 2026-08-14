<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Str;

it('returns the original order on a duplicate transaction_hash instead of creating a second one', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $payload = [
        'transaction_hash' => (string) Str::uuid(),
        'event_id' => $event->id,
        'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 2]],
        'name' => 'Jane Attendee',
        'email' => 'jane@example.test',
        'phone' => '+258840000000',
    ];

    $first = $this->postJson('/checkout', $payload);
    $first->assertCreated();

    $second = $this->postJson('/checkout', $payload);
    $second->assertOk();

    expect($second->json('order.id'))->toBe($first->json('order.id'))
        ->and(Order::count())->toBe(1)
        ->and($ticketType->fresh()->available_quantity)->toBe(8);
});
