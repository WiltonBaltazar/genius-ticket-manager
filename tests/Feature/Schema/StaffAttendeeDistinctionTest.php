<?php

use App\Models\Attendee;
use App\Models\Order;
use App\Models\Staff;
use App\Models\Ticket;

it('keeps Staff and Attendee as distinct models backed by distinct tables', function () {
    expect((new Staff)->getTable())->toBe('staff')
        ->and((new Attendee)->getTable())->toBe('attendees')
        ->and(Staff::class)->not->toBe(Attendee::class);

    $staff = Staff::factory()->create(['email' => 'distinct-test@example.com']);
    $attendee = Attendee::factory()->create(['email' => 'distinct-test@example.com']);

    // Same email is allowed across the two tables — they enforce uniqueness independently.
    expect($staff->id)->not->toBe($attendee->id)
        ->and(Staff::find($staff->id))->not->toBeNull()
        ->and(Attendee::find($attendee->id))->not->toBeNull();
});

it('always resolves ticket.checked_in_by and order.confirmed_by to a Staff instance, never an Attendee', function () {
    $staff = Staff::factory()->create();
    $ticket = Ticket::factory()->checkedIn()->create(['checked_in_by' => $staff->id]);
    $order = Order::factory()->create(['confirmed_by' => $staff->id, 'confirmed_at' => now()]);

    expect($ticket->checkedInBy)->toBeInstanceOf(Staff::class)
        ->and($ticket->checkedInBy)->not->toBeInstanceOf(Attendee::class)
        ->and($order->confirmedBy)->toBeInstanceOf(Staff::class)
        ->and($order->confirmedBy)->not->toBeInstanceOf(Attendee::class);
});
