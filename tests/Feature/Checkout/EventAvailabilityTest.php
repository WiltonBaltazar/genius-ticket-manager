<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;

it('shows price and available quantity for each ticket type on a published event', function () {
    $event = Event::factory()->create(['status' => EventStatus::Published]);
    $ticketType = TicketType::factory()->for($event)->create([
        'price' => 250,
        'total_quantity' => 100,
        'available_quantity' => 42,
    ]);

    $response = $this->getJson("/events/{$event->slug}");

    $response->assertOk();
    $response->assertJsonPath('event.id', $event->id);
    $response->assertJsonFragment([
        'id' => $ticketType->id,
        'price' => '250.00',
        'available_quantity' => 42,
    ]);
});

it('includes a sold-out ticket type rather than omitting it', function () {
    $event = Event::factory()->create(['status' => EventStatus::Published]);
    $soldOut = TicketType::factory()->for($event)->create([
        'total_quantity' => 10,
        'available_quantity' => 0,
    ]);

    $response = $this->getJson("/events/{$event->slug}");

    $response->assertOk();
    $response->assertJsonFragment(['id' => $soldOut->id, 'available_quantity' => 0]);
});

it('returns 404 for a draft, closed, or archived event', function () {
    foreach ([EventStatus::Draft, EventStatus::Closed, EventStatus::Archived] as $status) {
        $event = Event::factory()->create(['status' => $status]);

        $this->getJson("/events/{$event->slug}")->assertNotFound();
    }
});

it('returns 404 for a nonexistent event slug', function () {
    $this->getJson('/events/does-not-exist')->assertNotFound();
});
