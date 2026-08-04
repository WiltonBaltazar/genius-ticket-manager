<?php

use App\Models\Attendee;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('logs out an authenticated attendee, invalidating the session', function () {
    $attendee = Attendee::factory()->create();

    actingAs($attendee, 'web');

    postJson('/logout')->assertNoContent();

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('rejects logout when there is no active session', function () {
    postJson('/logout')->assertUnauthorized();
});
