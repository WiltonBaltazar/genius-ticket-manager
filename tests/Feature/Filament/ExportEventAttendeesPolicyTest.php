<?php

use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

it('allows exportAttendees for the same roles permitted to manage events', function () {
    $event = Event::factory()->create();

    foreach (['superAdmin', 'eventManager'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();
        expect($staff->can('exportAttendees', $event))->toBeTrue();
    }
});

it('refuses exportAttendees for support and gate_operator', function () {
    $event = Event::factory()->create();

    foreach (['support', 'gateOperator'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();
        expect($staff->can('exportAttendees', $event))->toBeFalse();
    }
});

it('shows the Export Attendees action on the event page for a permitted role', function () {
    $staff = Staff::factory()->eventManager()->create();
    $event = Event::factory()->create();

    $response = $this->actingAs($staff, 'staff')->get("/admin/events/{$event->id}");

    $response->assertOk();
    $response->assertSee('Export Attendees');
});

it('does not show the Export Attendees action for an unauthorized role', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $event = Event::factory()->create();

    $response = $this->actingAs($staff, 'staff')->get("/admin/events/{$event->id}");

    $response->assertForbidden();
});

it('downloads an XLSX with the right header row and attendee data when invoked through the admin panel', function () {
    $staff = Staff::factory()->eventManager()->create();
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'Geral']);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);
    $confirmingStaff = Staff::factory()->eventManager()->create();
    $confirmed = app(ConfirmOrderPaymentAction::class)->handle($order, $confirmingStaff);

    $component = Livewire::actingAs($staff, 'staff')
        ->test(ViewEvent::class, ['record' => $event->getKey()])
        ->callAction('exportAttendees')
        ->assertFileDownloaded("attendees-{$event->slug}-".now()->format('Y-m-d').'.xlsx');

    $bytes = base64_decode(data_get($component->effects, 'download.content'));
    $tempPath = tempnam(sys_get_temp_dir(), 'xlsx-test-').'.xlsx';
    file_put_contents($tempPath, $bytes);

    $rows = [];
    $reader = new Reader;
    $reader->open($tempPath);
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    unlink($tempPath);

    expect($rows[0])->toBe(['Name', 'Email', 'Phone', 'Ticket Type', 'Event Date', 'Status', 'Checked In At', 'Order Reference', 'Order Status'])
        ->and($rows[1])->toBe([
            $confirmed->attendee->name,
            $confirmed->attendee->email,
            $confirmed->attendee->phone,
            'Geral',
            '',
            'unused',
            '',
            strtoupper(substr($confirmed->id, 0, 8)),
            'paid',
        ]);
});
