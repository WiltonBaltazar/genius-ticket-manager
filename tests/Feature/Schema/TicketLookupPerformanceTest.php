<?php

use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Representative row count for a check-in-day scan pattern (plan.md Scale/Scope), sharing
    // parent rows so seeding 500 tickets doesn't cascade into 500 separate order/attendee chains.
    $type = TicketType::factory()->create(['total_quantity' => 600, 'available_quantity' => 100]);
    $item = OrderItem::factory()->for($type, 'ticketType')->create(['quantity' => 500]);
    Ticket::factory()->for($item, 'orderItem')->for($type, 'ticketType')->count(500)->create();
});

it('looks up a ticket by qr_code in well under one second, using its unique index', function () {
    $target = Ticket::factory()->create(['qr_code' => 'QR-PERF-TEST']);

    $start = microtime(true);
    $found = Ticket::with(['ticketType', 'orderItem'])->where('qr_code', 'QR-PERF-TEST')->first();
    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($found->id)->toBe($target->id)
        ->and($elapsedMs)->toBeLessThan(1000);

    $plan = DB::select('EXPLAIN SELECT * FROM tickets WHERE qr_code = ?', ['QR-PERF-TEST']);
    expect($plan[0]->key)->toBe('tickets_qr_code_unique');
});
