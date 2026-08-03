<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;

it('distinguishes a checked_in ticket (not voided) as already-used on a repeat scan', function () {
    $ticket = Ticket::factory()->checkedIn()->create();

    expect($ticket->status)->toBe(TicketStatus::CheckedIn)
        ->and($ticket->isCheckedIn())->toBeTrue()
        ->and($ticket->isVoided())->toBeFalse();

    // A "repeat scan" against an already-checked_in (not voided) ticket must be detectable as
    // already-used via this distinct status, independent of the voiding path tested in T058.
    $rescanned = Ticket::find($ticket->id);
    expect($rescanned->isCheckedIn())->toBeTrue();
});
