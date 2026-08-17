<?php

use App\Actions\Orders\OrderNotRefundableException;
use App\Actions\Orders\RefundOrderAction;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Notifications\Orders\OrderRefunded;
use Illuminate\Support\Facades\Notification;

it('refunds a paid order: sets refunded, records who and when, voids every ticket, and releases inventory', function () {
    $staff = Staff::factory()->eventManager()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 7]);
    $order = Order::factory()->create(); // Paid by default
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 3]);
    $tickets = Ticket::factory()->count(3)->create(['order_item_id' => $orderItem->id, 'ticket_type_id' => $ticketType->id]);

    $refunded = app(RefundOrderAction::class)->handle($order, $staff);

    expect($refunded->status)->toBe(OrderStatus::Refunded)
        ->and($refunded->refunded_by)->toBe($staff->id)
        ->and($refunded->refunded_at)->not->toBeNull();

    expect($refunded->tickets)->toHaveCount(3);
    expect($refunded->tickets->every(fn (Ticket $ticket) => $ticket->status === TicketStatus::Voided))->toBeTrue();

    expect($ticketType->fresh()->available_quantity)->toBe(10);
});

it('voids an already checked-in ticket rather than leaving it valid', function () {
    $staff = Staff::factory()->eventManager()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 9]);
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);
    Ticket::factory()->checkedIn()->create(['order_item_id' => $orderItem->id, 'ticket_type_id' => $ticketType->id]);

    $refunded = app(RefundOrderAction::class)->handle($order, $staff);

    expect($refunded->tickets->first()->status)->toBe(TicketStatus::Voided);
});

it('refuses to refund an order that is not paid', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->pending()->create();

    expect(fn () => app(RefundOrderAction::class)->handle($order, $staff))
        ->toThrow(OrderNotRefundableException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('refuses to refund an already-refunded order', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->refunded()->create();

    expect(fn () => app(RefundOrderAction::class)->handle($order, $staff))
        ->toThrow(OrderNotRefundableException::class);
});

it('writes an AuditLog row with the refunding staff attributed', function () {
    $staff = Staff::factory()->eventManager()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 9]);
    $order = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);

    app(RefundOrderAction::class)->handle($order, $staff);

    $log = $order->auditLogs()->latest()->first();
    expect($log->staff_id)->toBe($staff->id)
        ->and($log->action)->toBe('order.refunded');
});

it('notifies the attendee by email once the order is refunded', function () {
    Notification::fake();

    $staff = Staff::factory()->eventManager()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 9]);
    $order = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);

    app(RefundOrderAction::class)->handle($order, $staff);

    Notification::assertSentTo(
        $order->attendee,
        OrderRefunded::class,
        fn (OrderRefunded $notification) => $notification->toMail($order->attendee)->subject === 'O seu pedido foi reembolsado — '.config('app.name')
    );
});
