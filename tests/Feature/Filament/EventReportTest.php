<?php

use App\Actions\Events\BuildEventReportAction;
use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;
use Livewire\Livewire;

/**
 * One order with one item; confirmed (tickets issued) unless $confirm is false.
 */
function reportOrderFor(TicketType $ticketType, int $quantity, float $unitPrice, bool $confirm = true, array $orderOverrides = []): Order
{
    $order = Order::factory()->pending()->create($orderOverrides);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => $quantity * $unitPrice,
    ]);

    if (! $confirm) {
        return $order;
    }

    return app(ConfirmOrderPaymentAction::class)->handle($order, Staff::factory()->eventManager()->create());
}

it('counts only paid orders toward revenue and tickets sold, per ticket type', function () {
    $event = Event::factory()->create();
    $geral = TicketType::factory()->for($event)->create(['name' => 'Geral', 'price' => 100, 'total_quantity' => 50, 'available_quantity' => 50]);
    $vip = TicketType::factory()->for($event)->create(['name' => 'VIP', 'price' => 300, 'total_quantity' => 10, 'available_quantity' => 10]);

    reportOrderFor($geral, 2, 100);
    reportOrderFor($vip, 1, 300);
    reportOrderFor($geral, 5, 100, confirm: false);

    $report = app(BuildEventReportAction::class)->handle($event);

    expect($report['summary'])->toMatchArray([
        'revenue' => 500.0,
        'ticketsSold' => 3,
        'capacity' => 60,
        'capacityUsedPercent' => 5.0,
        'paidOrders' => 2,
        'averageOrderValue' => 250.0,
    ]);

    $byName = collect($report['ticketTypes'])->keyBy('name');
    expect($byName['Geral']['sold'])->toBe(2)
        ->and($byName['Geral']['revenue'])->toBe(200.0)
        ->and($byName['VIP']['sold'])->toBe(1)
        ->and($byName['VIP']['revenue'])->toBe(300.0);
});

it('breaks orders down by status and paid revenue by payment method', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    reportOrderFor($ticketType, 1, 100);
    reportOrderFor($ticketType, 1, 50, orderOverrides: ['payment_method' => 'offline']);
    reportOrderFor($ticketType, 2, 100, confirm: false);
    $refunded = reportOrderFor($ticketType, 1, 80);
    $refunded->update(['status' => OrderStatus::Refunded, 'refunded_at' => now()]);

    $report = app(BuildEventReportAction::class)->handle($event);

    $byStatus = collect($report['ordersByStatus'])->keyBy('status');
    expect($byStatus['paid'])->toMatchArray(['orders' => 2, 'amount' => 150.0])
        ->and($byStatus['pending'])->toMatchArray(['orders' => 1, 'amount' => 200.0])
        ->and($byStatus['refunded'])->toMatchArray(['orders' => 1, 'amount' => 80.0])
        ->and($byStatus['cancelled'])->toMatchArray(['orders' => 0, 'amount' => 0.0])
        ->and($report['summary']['revenue'])->toBe(150.0);

    $byMethod = collect($report['revenueByPaymentMethod'])->keyBy('method');
    expect($byMethod['mpesa'])->toMatchArray(['orders' => 1, 'revenue' => 100.0])
        ->and($byMethod['offline'])->toMatchArray(['orders' => 1, 'revenue' => 50.0]);
});

it('excludes another event\'s items, even inside the same order', function () {
    $event = Event::factory()->create();
    $other = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $otherType = TicketType::factory()->for($other)->create();

    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100]);
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $otherType->id, 'quantity' => 3, 'unit_price' => 200, 'subtotal' => 600]);
    app(ConfirmOrderPaymentAction::class)->handle($order, Staff::factory()->eventManager()->create());

    $report = app(BuildEventReportAction::class)->handle($event);

    expect($report['summary']['revenue'])->toBe(100.0)
        ->and($report['summary']['ticketsSold'])->toBe(1)
        ->and($report['checkInTotal']['issued'])->toBe(1);
});

it('groups paid sales by confirmation day with running totals', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $this->travelTo('2026-09-01 10:00:00');
    reportOrderFor($ticketType, 2, 100);
    $this->travelTo('2026-09-03 18:00:00');
    reportOrderFor($ticketType, 1, 100);
    reportOrderFor($ticketType, 1, 50);
    $this->travelBack();

    $report = app(BuildEventReportAction::class)->handle($event);

    expect($report['salesByDay'])->toBe([
        ['date' => '2026-09-01', 'tickets' => 2, 'revenue' => 200.0, 'cumulativeTickets' => 2, 'cumulativeRevenue' => 200.0],
        ['date' => '2026-09-03', 'tickets' => 2, 'revenue' => 150.0, 'cumulativeTickets' => 4, 'cumulativeRevenue' => 350.0],
    ]);
});

it('reports check-ins, no-shows and voided tickets, with the rate over valid tickets only', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'Geral']);

    $tickets = reportOrderFor($ticketType, 4, 100)->tickets;
    $tickets[0]->update(['status' => TicketStatus::CheckedIn, 'checked_in_at' => now()]);
    $tickets[1]->update(['status' => TicketStatus::CheckedIn, 'checked_in_at' => now()]);
    $tickets[2]->update(['status' => TicketStatus::Voided]);

    $report = app(BuildEventReportAction::class)->handle($event);

    $expected = ['issued' => 4, 'checkedIn' => 2, 'noShow' => 1, 'voided' => 1, 'rate' => 66.7];
    expect($report['checkInTotal'])->toBe($expected)
        ->and($report['checkInByTicketType'])->toBe([['label' => 'Geral'] + $expected])
        ->and($report['checkInByDate'])->toBe([]);
});

it('builds an empty report for an event with nothing sold', function () {
    $event = Event::factory()->create();

    $report = app(BuildEventReportAction::class)->handle($event);

    expect($report['summary']['revenue'])->toBe(0.0)
        ->and($report['summary']['capacityUsedPercent'])->toBe(0.0)
        ->and($report['salesByDay'])->toBe([])
        ->and($report['checkInTotal']['rate'])->toBe(0.0);
});

it('allows downloadReport for super_admin and event_manager only', function () {
    $event = Event::factory()->create();

    foreach (['superAdmin' => true, 'eventManager' => true, 'support' => false, 'gateOperator' => false] as $factoryState => $allowed) {
        $staff = Staff::factory()->{$factoryState}()->create();
        expect($staff->can('downloadReport', $event))->toBe($allowed);
    }
});

it('downloads the report PDF from the event page', function () {
    $staff = Staff::factory()->eventManager()->create();
    $event = Event::factory()->create();
    reportOrderFor(TicketType::factory()->for($event)->create(), 2, 100);

    $component = Livewire::actingAs($staff, 'staff')
        ->test(ViewEvent::class, ['record' => $event->getKey()])
        ->assertActionVisible('downloadReport')
        ->callAction('downloadReport')
        ->assertFileDownloaded("report-{$event->slug}-".now()->format('Y-m-d').'.pdf');

    expect(base64_decode(data_get($component->effects, 'download.content')))->toStartWith('%PDF');
});
