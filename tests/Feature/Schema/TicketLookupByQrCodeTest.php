<?php

use App\Models\Ticket;

it('looks up a ticket by qr_code with its relations eager-loadable', function () {
    $ticket = Ticket::factory()->create(['qr_code' => 'QR-LOOKUP-TEST']);

    $found = Ticket::with(['ticketType', 'orderItem'])->where('qr_code', 'QR-LOOKUP-TEST')->first();

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($ticket->id)
        ->and($found->relationLoaded('ticketType'))->toBeTrue()
        ->and($found->relationLoaded('orderItem'))->toBeTrue()
        ->and($found->ticketType->id)->toBe($ticket->ticket_type_id)
        ->and($found->orderItem->id)->toBe($ticket->order_item_id);
});
