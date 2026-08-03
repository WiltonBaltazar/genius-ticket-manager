<?php

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;

/*
 * The schema/model layer only needs to represent these states and make them distinguishable;
 * the workflow that actually triggers voiding on refund is business logic left to a future
 * feature (per spec.md's Assumptions). These tests simulate what that future logic would do.
 */

it('can reach voided from unused when its order is refunded', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create();
    $ticket = Ticket::factory()->for($item, 'orderItem')->create();

    expect($ticket->status)->toBe(TicketStatus::Unused);

    $order->update(['status' => OrderStatus::Refunded]);
    $ticket->update(['status' => TicketStatus::Voided]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Voided)
        ->and($ticket->fresh()->isVoided())->toBeTrue()
        ->and($ticket->fresh()->isCheckedIn())->toBeFalse();
});

it('can reach voided from checked_in when its order is refunded after check-in', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create();
    $ticket = Ticket::factory()->for($item, 'orderItem')->checkedIn()->create();

    expect($ticket->status)->toBe(TicketStatus::CheckedIn);

    $order->update(['status' => OrderStatus::Refunded]);
    $ticket->update(['status' => TicketStatus::Voided]);

    $fresh = $ticket->fresh();
    expect($fresh->status)->toBe(TicketStatus::Voided)
        ->and($fresh->isVoided())->toBeTrue()
        ->and($fresh->isCheckedIn())->toBeFalse()
        ->and($fresh->checked_in_at)->not->toBeNull(); // history of the prior check-in is preserved
});

it('distinguishes a voided ticket from a checked_in ticket for a subsequent check-in attempt', function () {
    $voided = Ticket::factory()->voided()->create();
    $checkedIn = Ticket::factory()->checkedIn()->create();

    expect($voided->isVoided())->toBeTrue()
        ->and($voided->isCheckedIn())->toBeFalse()
        ->and($checkedIn->isCheckedIn())->toBeTrue()
        ->and($checkedIn->isVoided())->toBeFalse();
});
