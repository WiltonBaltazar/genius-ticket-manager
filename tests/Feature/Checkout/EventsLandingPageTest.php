<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;

/*
 * The landing page shows a single event (the soonest-upcoming or currently
 * running active one), not a list. Explicit test events are given a
 * start_date well inside the next few days so they reliably beat any
 * leftover Event rows from the oversell concurrency tests (tests/Pest.php),
 * which commit real rows outside a transaction with a random start_date
 * between +1 week and +6 months and never clean them up.
 */

it('returns the active event with its on-sale ticket types', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addDay(),
        'end_date' => now()->addDay()->toDateString(),
    ]);
    $ticketType = TicketType::factory()->for($event)->create(['price' => 250]);

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $event->id);
    $response->assertJsonFragment([
        'id' => $ticketType->id,
        'price' => '250.00',
    ]);
});

it('returns the soonest-upcoming active event when more than one exists', function () {
    $soonest = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addDay(),
        'end_date' => now()->addDay()->toDateString(),
    ]);
    $later = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addMonth(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $soonest->id);
    expect($response->json('event.id'))->not->toBe($later->id);
});

it('prefers an event just edited to start today over an older, more-recently-created event dated further out', function () {
    $editedToStartToday = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addMonth(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);
    // Editing an existing row doesn't touch created_at — the picker must not
    // depend on it, or a same-day reschedule stays invisible on the landing
    // page behind whatever else was created most recently.
    $editedToStartToday->update([
        'start_date' => now(),
        'end_date' => now()->toDateString(),
    ]);

    Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addWeeks(2),
        'end_date' => now()->addWeeks(2)->toDateString(),
    ]);

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $editedToStartToday->id);
});

it('excludes a draft, closed, or archived event even if it is the soonest upcoming', function () {
    foreach ([EventStatus::Draft, EventStatus::Closed, EventStatus::Archived] as $status) {
        $event = Event::factory()->create([
            'status' => $status,
            'start_date' => now(),
            'end_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/');

        $response->assertOk();
        expect($response->json('event.id'))->not->toBe($event->id);
    }
});

it('excludes an event that has already finished', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->subMonth(),
        'end_date' => now()->subMonth()->toDateString(),
    ]);

    $response = $this->getJson('/');

    $response->assertOk();
    expect($response->json('event.id'))->not->toBe($event->id);
});

it('includes a multi-day event that already started but has not ended', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $event->id);
});

it('omits a ticket type that is not yet on sale', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now(),
        'end_date' => now()->toDateString(),
    ]);
    $notYetOnSale = TicketType::factory()->for($event)->create([
        'sales_start_date' => now()->addWeek(),
        'sales_end_date' => now()->addMonth(),
    ]);

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $event->id);
    $ticketTypeIds = collect($response->json('ticket_types'))->pluck('id');
    expect($ticketTypeIds)->not->toContain($notYetOnSale->id);
});

it('serves the SPA shell for a plain browser request', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('id="root"', false);
});
