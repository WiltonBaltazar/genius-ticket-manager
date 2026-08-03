<?php

use App\Enums\TicketStatus;
use App\Models\Staff;
use App\Models\Ticket;

it('records checked_in_at and checked_in_by when a ticket transitions from unused to checked_in', function () {
    $staff = Staff::factory()->create();
    $ticket = Ticket::factory()->create();

    expect($ticket->status)->toBe(TicketStatus::Unused)
        ->and($ticket->checked_in_at)->toBeNull();

    $ticket->update([
        'status' => TicketStatus::CheckedIn,
        'checked_in_at' => now(),
        'checked_in_by' => $staff->id,
    ]);

    $fresh = $ticket->fresh();

    expect($fresh->status)->toBe(TicketStatus::CheckedIn)
        ->and($fresh->checked_in_at)->not->toBeNull()
        ->and($fresh->checkedInBy->id)->toBe($staff->id)
        ->and($fresh->isCheckedIn())->toBeTrue();
});
