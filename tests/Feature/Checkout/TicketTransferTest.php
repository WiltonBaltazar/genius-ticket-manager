<?php

use App\Actions\Tickets\TicketNotTransferableException;
use App\Actions\Tickets\TransferTicketAction;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Notifications\Tickets\TicketTransferConfirmed;
use App\Notifications\Tickets\TicketTransferred;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

function transferableTicket(array $overrides = []): Ticket
{
    $order = Order::factory()->create(); // Paid by default
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);

    return Ticket::factory()->create(array_merge([
        'order_item_id' => $orderItem->id,
        'ticket_type_id' => $orderItem->ticket_type_id,
    ], $overrides));
}

it('transfers an unused ticket: sets the new holder, records when, and writes an AuditLog row', function () {
    $ticket = transferableTicket();

    $transferred = app(TransferTicketAction::class)->handle($ticket, 'Nova Pessoa', 'nova@example.test', '+258840000001');

    expect($transferred->holder_name)->toBe('Nova Pessoa')
        ->and($transferred->holder_email)->toBe('nova@example.test')
        ->and($transferred->holder_phone)->toBe('+258840000001')
        ->and($transferred->transferred_at)->not->toBeNull()
        ->and($transferred->currentHolderName())->toBe('Nova Pessoa')
        ->and($transferred->currentHolderEmail())->toBe('nova@example.test')
        ->and($transferred->currentHolderPhone())->toBe('+258840000001');

    $log = $ticket->auditLogs()->latest()->first();
    expect($log->action)->toBe('ticket.transferred')
        ->and($log->staff_id)->toBeNull();
});

it('currentHolderName/Email/Phone fall back to the order\'s attendee before any transfer', function () {
    $ticket = transferableTicket();
    $attendee = $ticket->orderItem->order->attendee;

    expect($ticket->currentHolderName())->toBe($attendee->name)
        ->and($ticket->currentHolderEmail())->toBe($attendee->email)
        ->and($ticket->currentHolderPhone())->toBe($attendee->phone);
});

it('refuses to transfer a checked-in ticket', function () {
    $ticket = transferableTicket(['status' => TicketStatus::CheckedIn, 'checked_in_at' => now()]);

    expect(fn () => app(TransferTicketAction::class)->handle($ticket, 'Nova Pessoa', 'nova@example.test', '+258840000001'))
        ->toThrow(TicketNotTransferableException::class);

    expect($ticket->fresh()->holder_name)->toBeNull();
});

it('refuses to transfer a voided ticket', function () {
    $ticket = transferableTicket(['status' => TicketStatus::Voided]);

    expect(fn () => app(TransferTicketAction::class)->handle($ticket, 'Nova Pessoa', 'nova@example.test', '+258840000001'))
        ->toThrow(TicketNotTransferableException::class);
});

it('notifies the new holder on demand and confirms the transfer to the original attendee', function () {
    Notification::fake();

    $ticket = transferableTicket();
    $originalAttendee = $ticket->orderItem->order->attendee;

    app(TransferTicketAction::class)->handle($ticket, 'Nova Pessoa', 'nova@example.test', '+258840000001');

    Notification::assertSentOnDemand(
        TicketTransferred::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'nova@example.test'
    );

    Notification::assertSentTo($originalAttendee, TicketTransferConfirmed::class);
});

it('transfers a ticket through the attendee-facing endpoint', function () {
    $ticket = transferableTicket();
    $order = $ticket->orderItem->order;

    postJson("/orders/{$order->id}/tickets/{$ticket->id}/transfer", [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.test',
        'phone' => '+258840000001',
    ])
        ->assertOk()
        ->assertJsonPath('ticket.holder_name', 'Nova Pessoa');

    expect($ticket->fresh()->holder_email)->toBe('nova@example.test')
        ->and($ticket->fresh()->holder_phone)->toBe('+258840000001');
});

it('rejects a transfer attempt for a ticket on a pending order', function () {
    $order = Order::factory()->pending()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);
    $ticket = Ticket::factory()->create(['order_item_id' => $orderItem->id, 'ticket_type_id' => $orderItem->ticket_type_id]);

    postJson("/orders/{$order->id}/tickets/{$ticket->id}/transfer", [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.test',
        'phone' => '+258840000001',
    ])->assertNotFound();
});

it('rejects a transfer attempt for a checked-in ticket with a distinct reason', function () {
    $ticket = transferableTicket(['status' => TicketStatus::CheckedIn, 'checked_in_at' => now()]);
    $order = $ticket->orderItem->order;

    postJson("/orders/{$order->id}/tickets/{$ticket->id}/transfer", [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.test',
        'phone' => '+258840000001',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'already_checked_in');
});

it('returns 404 when the ticket does not belong to the given order', function () {
    $ticket = transferableTicket();
    $otherOrder = Order::factory()->create();

    postJson("/orders/{$otherOrder->id}/tickets/{$ticket->id}/transfer", [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.test',
        'phone' => '+258840000001',
    ])->assertNotFound();
});

it('validates name, email, and phone are present and email is well-formed', function () {
    $ticket = transferableTicket();
    $order = $ticket->orderItem->order;

    postJson("/orders/{$order->id}/tickets/{$ticket->id}/transfer", ['name' => '', 'email' => 'not-an-email', 'phone' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'phone']);
});

it('throttles ticket transfer to 5 attempts per minute per IP', function () {
    RateLimiter::clear('ticket-transfer:127.0.0.1');

    $ticket = transferableTicket();
    $order = $ticket->orderItem->order;

    for ($i = 0; $i < 5; $i++) {
        postJson("/orders/{$order->id}/tickets/{$ticket->id}/transfer", [
            'name' => 'Nova Pessoa',
            'email' => "nova{$i}@example.test",
            'phone' => '+258840000001',
        ])->assertOk();
    }

    postJson("/orders/{$order->id}/tickets/{$ticket->id}/transfer", [
        'name' => 'Nova Pessoa',
        'email' => 'nova-final@example.test',
        'phone' => '+258840000001',
    ])->assertStatus(429);
});
