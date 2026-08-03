<?php

use App\Models\Event;

it('matches a two-day event when queried on either its start_date or end_date', function () {
    Event::factory()->create(['start_date' => '2027-06-01', 'end_date' => '2027-06-02']);

    $matchesDayOne = Event::where('start_date', '<=', '2027-06-01')->where('end_date', '>=', '2027-06-01')->count();
    $matchesDayTwo = Event::where('start_date', '<=', '2027-06-02')->where('end_date', '>=', '2027-06-02')->count();
    $missesDayThree = Event::where('start_date', '<=', '2027-06-03')->where('end_date', '>=', '2027-06-03')->count();

    expect($matchesDayOne)->toBe(1)
        ->and($matchesDayTwo)->toBe(1)
        ->and($missesDayThree)->toBe(0);
});
