<?php

use App\Models\Event;
use Illuminate\Database\QueryException;

it('allows a single-day event (end_date equals start_date)', function () {
    $event = Event::factory()->create(['start_date' => '2027-03-10', 'end_date' => '2027-03-10']);

    expect($event->fresh()->end_date->toDateString())->toBe('2027-03-10');
});

it('allows a two-day event (end_date one day after start_date)', function () {
    $event = Event::factory()->create(['start_date' => '2027-03-10', 'end_date' => '2027-03-11']);

    expect($event->fresh()->end_date->toDateString())->toBe('2027-03-11');
});

it('allows an end_date more than one day after start_date (multi-day events)', function () {
    $event = Event::factory()->create(['start_date' => '2027-03-10', 'end_date' => '2027-03-13']);

    expect($event->fresh()->end_date->toDateString())->toBe('2027-03-13');
});

it('rejects an end_date before start_date', function () {
    expect(fn () => Event::factory()->create(['start_date' => '2027-03-10', 'end_date' => '2027-03-09']))
        ->toThrow(QueryException::class);
});
