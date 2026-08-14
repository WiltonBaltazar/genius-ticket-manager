<?php

use App\Models\Attendee;

it('returns null when no attendee session is active', function () {
    $this->getJson('/session')->assertOk()->assertJsonPath('attendee', null);
});

it('returns the current attendee when a session is active', function () {
    $attendee = Attendee::factory()->create();

    $this->actingAs($attendee, 'web')
        ->getJson('/session')
        ->assertOk()
        ->assertJsonPath('attendee.id', $attendee->id)
        ->assertJsonPath('attendee.email', $attendee->email);
});
