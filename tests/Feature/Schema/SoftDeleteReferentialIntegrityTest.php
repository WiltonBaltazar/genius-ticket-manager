<?php

use App\Models\Event;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketType;

it('soft-deletes an event while its ticket types remain intact', function () {
    $event = Event::factory()->create();
    $type = TicketType::factory()->for($event)->create();

    $event->delete();

    expect(Event::find($event->id))->toBeNull()
        ->and(Event::withTrashed()->find($event->id))->not->toBeNull();

    expect(TicketType::find($type->id))->not->toBeNull()
        ->and($type->fresh()->event_id)->toBe($event->id);
});

it('soft-deletes a ticket type while existing tickets remain intact', function () {
    $type = TicketType::factory()->create();
    $ticket = Ticket::factory()->for($type, 'ticketType')->create();

    $type->delete();

    expect(TicketType::find($type->id))->toBeNull()
        ->and(TicketType::withTrashed()->find($type->id))->not->toBeNull();

    expect(Ticket::find($ticket->id))->not->toBeNull()
        ->and($ticket->fresh()->ticket_type_id)->toBe($type->id);
});

it('soft-deletes a staff record while its historical check-in references remain intact', function () {
    $staff = Staff::factory()->create();
    $ticket = Ticket::factory()->checkedIn()->create(['checked_in_by' => $staff->id]);

    $staff->delete();

    expect(Staff::find($staff->id))->toBeNull()
        ->and(Staff::withTrashed()->find($staff->id))->not->toBeNull();

    expect($ticket->fresh()->checked_in_by)->toBe($staff->id)
        ->and($ticket->fresh()->checkedInBy->id)->toBe($staff->id);
});
