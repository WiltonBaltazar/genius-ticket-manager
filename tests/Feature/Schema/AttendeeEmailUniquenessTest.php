<?php

use App\Models\Attendee;
use Illuminate\Database\QueryException;

it('rejects a second non-deleted attendee with the same email', function () {
    Attendee::factory()->create(['email' => 'baseline-unique-test@example.com']);

    expect(fn () => Attendee::factory()->create(['email' => 'baseline-unique-test@example.com']))
        ->toThrow(QueryException::class);

    expect(Attendee::where('email', 'baseline-unique-test@example.com')->count())->toBe(1);
});
