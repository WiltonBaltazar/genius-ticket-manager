<?php

use App\Actions\Events\ExportEventAttendeesAction;
use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Actions\Tickets\TransferTicketAction;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;

function paidTicketFor(Event $event, array $ticketTypeOverrides = []): array
{
    $ticketType = TicketType::factory()->for($event)->create($ticketTypeOverrides);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);
    $staff = Staff::factory()->eventManager()->create();
    $confirmed = app(ConfirmOrderPaymentAction::class)->handle($order, $staff);

    return [$confirmed, $confirmed->tickets->first()];
}

it('exports one row per ticket with the current holder\'s name/email/phone', function () {
    $event = Event::factory()->create();
    [$order, $ticket] = paidTicketFor($event, ['name' => 'Geral']);

    $rows = app(ExportEventAttendeesAction::class)->handle($event);

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row['Name'])->toBe($order->attendee->name)
        ->and($row['Email'])->toBe($order->attendee->email)
        ->and($row['Phone'])->toBe($order->attendee->phone)
        ->and($row['Ticket Type'])->toBe('Geral')
        ->and($row['Status'])->toBe('unused')
        ->and($row['Order Status'])->toBe('paid')
        ->and($row['Order Reference'])->toBe(strtoupper(substr($order->id, 0, 8)));
});

it('reflects the new holder on a transferred ticket, not the order\'s own attendee', function () {
    $event = Event::factory()->create();
    [, $ticket] = paidTicketFor($event);

    app(TransferTicketAction::class)->handle($ticket, 'Nova Pessoa', 'nova@example.test', '+258840000001');

    $rows = app(ExportEventAttendeesAction::class)->handle($event);

    expect($rows->first()['Name'])->toBe('Nova Pessoa')
        ->and($rows->first()['Email'])->toBe('nova@example.test')
        ->and($rows->first()['Phone'])->toBe('+258840000001');
});

it('only includes tickets for the given event, not other events\' tickets', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();
    paidTicketFor($eventA);
    paidTicketFor($eventB);

    $rows = app(ExportEventAttendeesAction::class)->handle($eventA);

    expect($rows)->toHaveCount(1);
});

it('returns an empty collection for an event with no tickets', function () {
    $event = Event::factory()->create();

    $rows = app(ExportEventAttendeesAction::class)->handle($event);

    expect($rows)->toHaveCount(0);
});
