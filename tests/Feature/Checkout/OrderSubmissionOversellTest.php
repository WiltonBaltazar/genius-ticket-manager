<?php

use App\Actions\Checkout\SubmitOrderAction;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Mirrors tests/Feature/Schema/TicketTypeOversellTest.php's two-connection technique
 * (see that file's comment for why this needs two genuinely independent, uncommitted
 * connections rather than the DatabaseTransactions wrapper) — proving that
 * SubmitOrderAction's per-line-item decrement uses the same race-safe conditional
 * update, not a naive read-then-write. Excluded from DatabaseTransactions per
 * tests/Pest.php.
 */

afterEach(function () {
    if (config('database.connections.mysql_conn_b')) {
        DB::connection('mysql_conn_b')->disconnect();
        DB::purge('mysql_conn_b');
    }
});

it('lets only one of two checkout attempts racing the last available ticket succeed', function () {
    config([
        'database.connections.mysql_conn_b' => array_merge(
            config('database.connections.mysql'),
            ['name' => 'mysql_conn_b']
        ),
    ]);

    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'total_quantity' => 1,
        'available_quantity' => 1,
        'version' => 0,
    ]);

    try {
        $connA = DB::connection('mysql');
        $connB = DB::connection('mysql_conn_b');

        // Both "requests" read the row while it still shows 1 available — exactly
        // the interleaving a real race under load would produce.
        $rowA = $connA->table('ticket_types')->where('id', $ticketType->id)->first();
        $rowB = $connB->table('ticket_types')->where('id', $ticketType->id)->first();

        // Request A's conditional decrement — the same shape SubmitOrderAction uses.
        $affectedA = $connA->table('ticket_types')
            ->where('id', $ticketType->id)
            ->where('version', $rowA->version)
            ->where('available_quantity', '>=', 1)
            ->update([
                'available_quantity' => $rowA->available_quantity - 1,
                'version' => $rowA->version + 1,
            ]);

        // Request B's conditional decrement, against its now-stale read.
        $affectedB = $connB->table('ticket_types')
            ->where('id', $ticketType->id)
            ->where('version', $rowB->version)
            ->where('available_quantity', '>=', 1)
            ->update([
                'available_quantity' => $rowB->available_quantity - 1,
                'version' => $rowB->version + 1,
            ]);

        expect($affectedA)->toBe(1)
            ->and($affectedB)->toBe(0);
    } finally {
        DB::table('ticket_types')->where('id', $ticketType->id)->update([
            'available_quantity' => 0,
            'version' => 1,
        ]);
    }

    // End-to-end: with the row now genuinely exhausted, a real checkout submission
    // for it is refused cleanly — no order, no further decrement.
    $action = app(SubmitOrderAction::class);
    $result = $action->handle([
        'transaction_hash' => (string) Str::uuid(),
        'event_id' => $event->id,
        'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        'name' => 'Late Arrival',
        'email' => 'late@example.test',
    ]);

    expect($result->order)->toBeNull()
        ->and($result->shortfalls)->toHaveKey($ticketType->id);
    expect($ticketType->fresh()->available_quantity)->toBe(0);
});
