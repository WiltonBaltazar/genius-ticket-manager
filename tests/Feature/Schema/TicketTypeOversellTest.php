<?php

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/*
 * This test is intentionally excluded from the DatabaseTransactions wrapper (see tests/Pest.php)
 * because it needs two genuinely independent, uncommitted connections racing the same row — a
 * single wrapping transaction would serialize the two updates and mask the very race this test
 * exists to catch. It cleans up its own rows manually instead.
 */

afterEach(function () {
    if (config('database.connections.mysql_conn_b')) {
        DB::connection('mysql_conn_b')->disconnect();
        DB::purge('mysql_conn_b');
    }
});

it('allows only one of two concurrent version-matched decrements to succeed', function () {
    config([
        'database.connections.mysql_conn_b' => array_merge(
            config('database.connections.mysql'),
            ['name' => 'mysql_conn_b']
        ),
    ]);

    $event = Event::factory()->create();
    $type = TicketType::factory()->for($event)->create([
        'total_quantity' => 1,
        'available_quantity' => 1,
        'version' => 0,
    ]);

    try {
        // Connection A and Connection B both "read" the row while available_quantity=1, version=0.
        $connA = DB::connection('mysql');
        $connB = DB::connection('mysql_conn_b');

        $rowA = $connA->table('ticket_types')->where('id', $type->id)->first();
        $rowB = $connB->table('ticket_types')->where('id', $type->id)->first();

        expect($rowA->available_quantity)->toBe(1)
            ->and($rowB->available_quantity)->toBe(1)
            ->and($rowA->version)->toBe(0)
            ->and($rowB->version)->toBe(0);

        // Connection A commits its version-matched decrement first.
        $affectedA = $connA->table('ticket_types')
            ->where('id', $type->id)
            ->where('version', $rowA->version)
            ->where('available_quantity', '>', 0)
            ->update([
                'available_quantity' => $rowA->available_quantity - 1,
                'version' => $rowA->version + 1,
            ]);

        // Connection B then attempts the same conditional update against its now-stale version.
        $affectedB = $connB->table('ticket_types')
            ->where('id', $type->id)
            ->where('version', $rowB->version)
            ->where('available_quantity', '>', 0)
            ->update([
                'available_quantity' => $rowB->available_quantity - 1,
                'version' => $rowB->version + 1,
            ]);

        expect($affectedA)->toBe(1)
            ->and($affectedB)->toBe(0);

        $final = DB::connection('mysql')->table('ticket_types')->where('id', $type->id)->first();
        expect($final->available_quantity)->toBe(0)
            ->and($final->version)->toBe(1);
    } finally {
        DB::table('ticket_types')->where('id', $type->id)->delete();
        DB::table('events')->where('id', $event->id)->delete();
    }
});

it('rejects a purchase attempt when available_quantity is already zero', function () {
    $event = Event::factory()->create();
    $type = TicketType::factory()->for($event)->create([
        'total_quantity' => 1,
        'available_quantity' => 0,
        'version' => 5,
    ]);

    try {
        $affected = DB::table('ticket_types')
            ->where('id', $type->id)
            ->where('version', 5)
            ->where('available_quantity', '>', 0)
            ->update([
                'available_quantity' => -1,
                'version' => 6,
            ]);

        expect($affected)->toBe(0);

        $final = DB::table('ticket_types')->where('id', $type->id)->first();
        expect($final->available_quantity)->toBe(0)
            ->and($final->version)->toBe(5);
    } finally {
        DB::table('ticket_types')->where('id', $type->id)->delete();
        DB::table('events')->where('id', $event->id)->delete();
    }
});
