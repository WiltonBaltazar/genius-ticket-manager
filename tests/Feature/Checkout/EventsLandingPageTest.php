<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Testing\TestResponse;

/*
 * Assertions here look up a specific event by id in the response rather than
 * asserting on the full list's count/order, because the two oversell
 * concurrency tests (tests/Pest.php) commit real Event rows outside any
 * transaction and never clean them up — the events table isn't guaranteed
 * empty at the start of these tests.
 */
function eventIdsIn(TestResponse $response): array
{
    return collect($response->json('events'))->pluck('id')->all();
}

it('lists a published upcoming event with a starting price from its cheapest on-sale ticket type', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addWeek(),
        'end_date' => now()->addWeek()->toDateString(),
    ]);
    TicketType::factory()->for($event)->create(['price' => 300]);
    TicketType::factory()->for($event)->create(['price' => 150]);

    $response = $this->getJson('/');

    $response->assertOk();
    expect(eventIdsIn($response))->toContain($event->id);
    $entry = collect($response->json('events'))->firstWhere('id', $event->id);
    expect($entry['starting_price'])->toBe('150.00');
});

it('orders two published upcoming events soonest first', function () {
    $later = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addMonth(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);
    $sooner = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->addWeek(),
        'end_date' => now()->addWeek()->toDateString(),
    ]);

    $ids = eventIdsIn($this->getJson('/'));

    expect(array_search($sooner->id, $ids, true))
        ->toBeLessThan(array_search($later->id, $ids, true));
});

it('excludes draft, closed, and archived events', function () {
    $excludedIds = [];
    foreach ([EventStatus::Draft, EventStatus::Closed, EventStatus::Archived] as $status) {
        $excludedIds[] = Event::factory()->create(['status' => $status])->id;
    }

    $ids = eventIdsIn($this->getJson('/'));

    expect(array_intersect($excludedIds, $ids))->toBeEmpty();
});

it('excludes an event that has already finished', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->subMonth(),
        'end_date' => now()->subMonth()->toDateString(),
    ]);

    expect(eventIdsIn($this->getJson('/')))->not->toContain($event->id);
});

it('includes a multi-day event that already started but has not ended', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    expect(eventIdsIn($this->getJson('/')))->toContain($event->id);
});

it('reports a null starting price when no ticket type is currently on sale', function () {
    $event = Event::factory()->create(['status' => EventStatus::Published]);
    TicketType::factory()->for($event)->create([
        'sales_start_date' => now()->addWeek(),
        'sales_end_date' => now()->addMonth(),
    ]);

    $response = $this->getJson('/');

    $entry = collect($response->json('events'))->firstWhere('id', $event->id);
    expect($entry['starting_price'])->toBeNull();
});

it('serves the SPA shell for a plain browser request', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('id="root"', false);
});
