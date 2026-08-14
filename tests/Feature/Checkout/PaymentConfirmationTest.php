<?php

use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Actions\Orders\OrderNotPendingException;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;

it('confirms a pending order: sets paid, records who and when, and issues one ticket per unit purchased', function () {
    $staff = Staff::factory()->eventManager()->create();
    $ticketType = TicketType::factory()->create();
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 3]);

    $confirmed = app(ConfirmOrderPaymentAction::class)->handle($order, $staff);

    expect($confirmed->status)->toBe(OrderStatus::Paid)
        ->and($confirmed->confirmed_by)->toBe($staff->id)
        ->and($confirmed->confirmed_at)->not->toBeNull();

    expect($confirmed->tickets)->toHaveCount(3);
    expect($confirmed->tickets->pluck('qr_code')->unique())->toHaveCount(3);
});

it('refuses to confirm an order that is not pending', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->create(); // Paid by default

    expect(fn () => app(ConfirmOrderPaymentAction::class)->handle($order, $staff))
        ->toThrow(OrderNotPendingException::class);

    expect($order->fresh()->tickets)->toHaveCount(0);
});

it('refuses to confirm an expired order', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->expired()->create();

    expect(fn () => app(ConfirmOrderPaymentAction::class)->handle($order, $staff))
        ->toThrow(OrderNotPendingException::class);
});

it('writes an AuditLog row with the confirming staff attributed', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id]);

    app(ConfirmOrderPaymentAction::class)->handle($order, $staff);

    $log = $order->auditLogs()->latest()->first();
    expect($log->staff_id)->toBe($staff->id);
});
