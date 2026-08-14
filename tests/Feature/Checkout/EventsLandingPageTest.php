<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * The landing page shows a single event (the most recently created active
 * one), not a list — so "latest" needs deterministic created_at control in
 * these tests. Two oversell concurrency tests (tests/Pest.php) commit real
 * Event rows outside any transaction and never clean them up, so the events
 * table isn't guaranteed empty; setting created_at far in the future makes a
 * test event unambiguously "the latest" regardless of that leftover data.
 */
function withCreatedAt(Event $event, Carbon $timestamp): Event
{
    DB::table('events')->where('id', $event->id)->update(['created_at' => $timestamp]);

    return $event->fresh();
}

it('returns the active event with its on-sale ticket types', function () {
    $event = withCreatedAt(
        Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->toDateString(),
        ]),
        now()->addYear(),
    );
    $ticketType = TicketType::factory()->for($event)->create(['price' => 250]);

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $event->id);
    $response->assertJsonFragment([
        'id' => $ticketType->id,
        'price' => '250.00',
    ]);
});

it('returns the most recently created active event when more than one exists', function () {
    $older = withCreatedAt(
        Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->toDateString(),
        ]),
        now()->subDay(),
    );
    $newer = withCreatedAt(
        Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->addMonth(),
            'end_date' => now()->addMonth()->toDateString(),
        ]),
        now()->addYear(),
    );

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $newer->id);
    expect($response->json('event.id'))->not->toBe($older->id);
});

it('excludes a draft, closed, or archived event even if it is the most recently created', function () {
    foreach ([EventStatus::Draft, EventStatus::Closed, EventStatus::Archived] as $status) {
        $event = withCreatedAt(Event::factory()->create(['status' => $status]), now()->addYear());

        $response = $this->getJson('/');

        $response->assertOk();
        expect($response->json('event.id'))->not->toBe($event->id);
    }
});

it('excludes an event that has already finished even if it is the most recently created', function () {
    $event = withCreatedAt(
        Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->subMonth(),
            'end_date' => now()->subMonth()->toDateString(),
        ]),
        now()->addYear(),
    );

    $response = $this->getJson('/');

    $response->assertOk();
    expect($response->json('event.id'))->not->toBe($event->id);
});

it('includes a multi-day event that already started but has not ended', function () {
    $event = withCreatedAt(
        Event::factory()->create([
            'status' => EventStatus::Published,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay()->toDateString(),
        ]),
        now()->addYear(),
    );

    $response = $this->getJson('/');

    $response->assertOk();
    $response->assertJsonPath('event.id', $event->id);
});

it('omits a ticket type that is not yet on sale', function () {
    $event = withCreatedAt(Event::factory()->create(['status' => EventStatus::Published]), now()->addYear());
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
