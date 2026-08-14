<?php

use Illuminate\Support\Facades\DB;

function assertIndexExists(string $table, string $indexName): void
{
    $rows = DB::select(
        'SELECT COUNT(*) AS cnt FROM information_schema.statistics '
        .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$table, $indexName]
    );

    expect((int) $rows[0]->cnt)->toBeGreaterThan(0, "Expected index [{$indexName}] on [{$table}] to exist.");
}

it('has the events(status, start_date) index', function () {
    assertIndexExists('events', 'events_status_start_date_index');
});

it('has the ticket_types(event_id) index', function () {
    assertIndexExists('ticket_types', 'ticket_types_event_id_foreign');
});

it('has the orders(attendee_id, status) index', function () {
    assertIndexExists('orders', 'orders_attendee_id_status_index');
});

it('has the constitution-mandated orders(attendee_id, created_at DESC) index', function () {
    assertIndexExists('orders', 'orders_attendee_id_created_at_desc_index');

    $rows = DB::select("SHOW INDEX FROM orders WHERE Key_name = 'orders_attendee_id_created_at_desc_index'");
    $byColumn = collect($rows)->keyBy('Column_name');

    expect($byColumn['attendee_id']->Collation)->toBe('A')
        ->and($byColumn['created_at']->Collation)->toBe('D');
});

it('has the orders(payment_reference) unique index', function () {
    assertIndexExists('orders', 'orders_payment_reference_unique');
});

it('has the tickets(qr_code) unique index', function () {
    assertIndexExists('tickets', 'tickets_qr_code_unique');
});
