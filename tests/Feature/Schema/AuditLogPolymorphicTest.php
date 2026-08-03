<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Staff;
use App\Models\Ticket;

it('resolves the polymorphic auditable relation to an Order', function () {
    $order = Order::factory()->create();
    $log = AuditLog::factory()->for($order, 'auditable')->create();

    expect($log->auditable)->toBeInstanceOf(Order::class)
        ->and($log->auditable->id)->toBe($order->id)
        ->and($order->auditLogs()->first()->id)->toBe($log->id);
});

it('resolves the polymorphic auditable relation to a Ticket', function () {
    $ticket = Ticket::factory()->create();
    $log = AuditLog::factory()->for($ticket, 'auditable')->create();

    expect($log->auditable)->toBeInstanceOf(Ticket::class)
        ->and($log->auditable->id)->toBe($ticket->id)
        ->and($ticket->auditLogs()->first()->id)->toBe($log->id);
});

it('resolves the polymorphic auditable relation to a Staff record', function () {
    $staff = Staff::factory()->create();
    $log = AuditLog::factory()->for($staff, 'auditable')->create();

    expect($log->auditable)->toBeInstanceOf(Staff::class)
        ->and($log->auditable->id)->toBe($staff->id);
});
