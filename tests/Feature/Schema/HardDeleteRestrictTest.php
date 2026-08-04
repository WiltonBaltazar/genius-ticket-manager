<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\QueryException;

it('rejects hard-deleting an event referenced by a non-soft-deleted ticket type', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create();

    expect(fn () => $event->forceDelete())->toThrow(QueryException::class);
});

it('rejects hard-deleting a ticket type referenced by a non-soft-deleted order item', function () {
    $type = TicketType::factory()->create();
    OrderItem::factory()->for($type, 'ticketType')->create();

    expect(fn () => $type->forceDelete())->toThrow(QueryException::class);
});

it('rejects hard-deleting an attendee referenced by a non-soft-deleted order', function () {
    $attendee = Attendee::factory()->create();
    Order::factory()->for($attendee)->create();

    expect(fn () => $attendee->forceDelete())->toThrow(QueryException::class);
});

it('rejects hard-deleting a staff record referenced by a non-soft-deleted ticket check-in', function () {
    $staff = Staff::factory()->create();
    Ticket::factory()->checkedIn()->create(['checked_in_by' => $staff->id]);

    expect(fn () => $staff->forceDelete())->toThrow(QueryException::class);
});
