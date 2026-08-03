<?php

use App\Models\Attendee;

it('allows a new attendee to register with an email freed by a prior soft delete', function () {
    $original = Attendee::factory()->create(['email' => 'reuse-test@example.com']);
    $original->delete();

    $replacement = Attendee::factory()->create(['email' => 'reuse-test@example.com']);

    expect($replacement->exists)->toBeTrue()
        ->and($replacement->id)->not->toBe($original->id)
        ->and(Attendee::withTrashed()->where('email', 'reuse-test@example.com')->count())->toBe(2);
});
