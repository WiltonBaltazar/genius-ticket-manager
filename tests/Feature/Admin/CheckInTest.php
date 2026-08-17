<?php

use App\Enums\TicketStatus;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\Ticket;

function ticketFor(Attendee $attendee, array $ticketOverrides = []): Ticket
{
    $order = Order::factory()->for($attendee)->create();
    $item = OrderItem::factory()->for($order)->create();

    return Ticket::factory()
        ->for($item, 'orderItem')
        ->for($item->ticketType, 'ticketType')
        ->create($ticketOverrides);
}

it('looks up a ticket by exact qr_code', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create(['name' => 'Maria Silva']);
    $ticket = ticketFor($attendee, ['qr_code' => 'QR-LOOKUP-1']);

    $response = $this->actingAs($staff, 'staff')->getJson('/admin/check-in/lookup?qr_code=QR-LOOKUP-1');

    $response->assertOk();
    $response->assertJsonCount(1, 'tickets');
    $response->assertJsonPath('tickets.0.id', $ticket->id);
    $response->assertJsonPath('tickets.0.attendee_name', 'Maria Silva');
    $response->assertJsonPath('tickets.0.status', 'unused');
});

it('returns no tickets for an unknown qr_code', function () {
    $staff = Staff::factory()->gateOperator()->create();

    $response = $this->actingAs($staff, 'staff')->getJson('/admin/check-in/lookup?qr_code=NOPE');

    $response->assertOk();
    $response->assertJsonCount(0, 'tickets');
});

it('finds a ticket by attendee name, email, or phone via manual search', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create([
        'name' => 'Carlos Mendes',
        'email' => 'carlos@example.test',
        'phone' => '+258849990000',
    ]);
    $ticket = ticketFor($attendee);

    foreach (['Carlos', 'carlos@example.test', '849990000'] as $query) {
        $response = $this->actingAs($staff, 'staff')->getJson('/admin/check-in/lookup?q='.urlencode($query));
        $response->assertOk();
        $response->assertJsonPath('tickets.0.id', $ticket->id);
    }
});

it('finds a ticket by its order reference via manual search', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create();
    $ticket = ticketFor($attendee);
    $reference = strtoupper(substr($ticket->orderItem->order_id, 0, 8));

    $response = $this->actingAs($staff, 'staff')->getJson('/admin/check-in/lookup?q='.$reference);

    $response->assertOk();
    $response->assertJsonPath('tickets.0.id', $ticket->id);
});

it('confirms check-in for an unused ticket, recording who and when', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create();
    $ticket = ticketFor($attendee);

    $response = $this->actingAs($staff, 'staff')->postJson("/admin/check-in/tickets/{$ticket->id}/confirm");

    $response->assertOk();
    $response->assertJsonPath('ticket.status', 'checked_in');

    $fresh = $ticket->fresh();
    expect($fresh->status)->toBe(TicketStatus::CheckedIn)
        ->and($fresh->checked_in_by)->toBe($staff->id)
        ->and($fresh->checked_in_at)->not->toBeNull();

    $log = AuditLog::where('auditable_id', $ticket->id)->where('action', 'ticket.checked_in')->first();
    expect($log)->not->toBeNull()
        ->and($log->staff_id)->toBe($staff->id);
});

it('rejects confirming an already checked-in ticket without changing its original checked_in_at', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create();
    $originalCheckedInAt = now()->subHour();
    $ticket = ticketFor($attendee, [
        'status' => TicketStatus::CheckedIn,
        'checked_in_at' => $originalCheckedInAt,
    ]);

    $response = $this->actingAs($staff, 'staff')->postJson("/admin/check-in/tickets/{$ticket->id}/confirm");

    $response->assertUnprocessable();
    $response->assertJsonPath('error', 'already_checked_in');
    // Compared at second precision, not exact Carbon equality — the `timestamp`
    // column truncates microseconds on save, so $originalCheckedInAt itself
    // wouldn't round-trip exactly even if this assertion were about something
    // that hadn't changed.
    expect($ticket->fresh()->checked_in_at->toDateTimeString())->toBe($originalCheckedInAt->toDateTimeString());
});

it('rejects confirming a voided ticket', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create();
    $ticket = ticketFor($attendee, ['status' => TicketStatus::Voided]);

    $response = $this->actingAs($staff, 'staff')->postJson("/admin/check-in/tickets/{$ticket->id}/confirm");

    $response->assertUnprocessable();
    $response->assertJsonPath('error', 'voided');
    expect($ticket->fresh()->status)->toBe(TicketStatus::Voided);
});

it('rejects confirming a day-pass ticket scanned on the wrong day, leaving it unused', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create();
    $ticket = ticketFor($attendee, ['event_date' => now()->addDays(2)->toDateString()]);

    $response = $this->actingAs($staff, 'staff')->postJson("/admin/check-in/tickets/{$ticket->id}/confirm");

    $response->assertUnprocessable();
    $response->assertJsonPath('error', 'wrong_day');
    expect($ticket->fresh()->status)->toBe(TicketStatus::Unused);
});

it('confirms a day-pass ticket scanned on its own day', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create();
    $ticket = ticketFor($attendee, ['event_date' => now()->toDateString()]);

    $response = $this->actingAs($staff, 'staff')->postJson("/admin/check-in/tickets/{$ticket->id}/confirm");

    $response->assertOk();
    expect($ticket->fresh()->status)->toBe(TicketStatus::CheckedIn);
});

it('shows the transferred holder\'s name, not the order attendee\'s, once a ticket has been transferred', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create(['name' => 'Maria Silva']);
    $ticket = ticketFor($attendee, [
        'qr_code' => 'QR-TRANSFERRED-1',
        'holder_name' => 'Nova Pessoa',
        'holder_email' => 'nova@example.test',
        'transferred_at' => now(),
    ]);

    $response = $this->actingAs($staff, 'staff')->getJson('/admin/check-in/lookup?qr_code=QR-TRANSFERRED-1');

    $response->assertOk();
    $response->assertJsonPath('tickets.0.attendee_name', 'Nova Pessoa');
});

it('finds a transferred ticket by the new holder\'s name via manual search', function () {
    $staff = Staff::factory()->gateOperator()->create();
    $attendee = Attendee::factory()->create(['name' => 'Maria Silva']);
    $ticket = ticketFor($attendee, [
        'holder_name' => 'Carlos Novo',
        'holder_email' => 'carlos@example.test',
        'transferred_at' => now(),
    ]);

    $response = $this->actingAs($staff, 'staff')->getJson('/admin/check-in/lookup?q=Carlos');

    $response->assertOk();
    $response->assertJsonCount(1, 'tickets');
    $response->assertJsonPath('tickets.0.id', $ticket->id);
});

it('forbids lookup and confirm for an unauthorized role', function () {
    $staff = Staff::factory()->support()->create();
    $attendee = Attendee::factory()->create();
    $ticket = ticketFor($attendee);

    $this->actingAs($staff, 'staff')->getJson('/admin/check-in/lookup?qr_code=x')->assertForbidden();
    $this->actingAs($staff, 'staff')->postJson("/admin/check-in/tickets/{$ticket->id}/confirm")->assertForbidden();
});
