<?php

use Illuminate\Support\Facades\DB;

it('creates every core table with InnoDB engine and utf8mb4_unicode_ci collation', function () {
    $tables = [
        'attendees', 'events', 'ticket_types', 'staff',
        'orders', 'order_items', 'tickets', 'audit_logs', 'payment_events',
    ];

    $rows = DB::select(
        'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.tables '
        .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('
        .implode(',', array_fill(0, count($tables), '?')).')',
        $tables
    );

    expect($rows)->toHaveCount(count($tables));

    foreach ($rows as $row) {
        expect($row->ENGINE)->toBe('InnoDB')
            ->and($row->TABLE_COLLATION)->toBe('utf8mb4_unicode_ci');
    }
});
