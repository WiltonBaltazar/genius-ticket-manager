<?php

use App\Filament\Resources\TicketTypes\Pages\CreateTicketType;
use App\Filament\Resources\TicketTypes\Pages\EditTicketType;
use App\Models\Event;
use App\Models\Staff;
use App\Models\TicketType;
use Livewire\Livewire;

it('lets event_manager create a ticket type with a sales window, linked to its event', function () {
    $event = Event::factory()->create();
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateTicketType::class)
        ->fillForm([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'description' => 'Standard entry.',
            'price' => 250,
            'total_quantity' => 100,
            'sales_start_date' => now()->format('Y-m-d H:i:s'),
            'sales_end_date' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ticketType = TicketType::where('event_id', $event->id)->first();
    expect($ticketType)->not->toBeNull()
        ->and($ticketType->total_quantity)->toBe(100)
        ->and($ticketType->available_quantity)->toBe(100);
});

it('rejects a sales end date before the sales start date', function () {
    $event = Event::factory()->create();
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateTicketType::class)
        ->fillForm([
            'event_id' => $event->id,
            'name' => 'Bad Window',
            'price' => 100,
            'total_quantity' => 10,
            'sales_start_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'sales_end_date' => now()->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertHasFormErrors(['sales_end_date']);
});

it('keeps total_quantity editable while no tickets have sold', function () {
    $ticketType = TicketType::factory()->create(['total_quantity' => 50, 'available_quantity' => 50]);
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(EditTicketType::class, ['record' => $ticketType->getKey()])
        ->fillForm(['total_quantity' => 75])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($ticketType->fresh()->total_quantity)->toBe(75);
});

it('locks total_quantity as read-only once any ticket has sold', function () {
    $ticketType = TicketType::factory()->create(['total_quantity' => 50, 'available_quantity' => 40]);
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(EditTicketType::class, ['record' => $ticketType->getKey()])
        ->fillForm(['total_quantity' => 999])
        ->call('save');

    expect($ticketType->fresh()->total_quantity)->toBe(50);
});

it('closes the race: a sale recorded after the form loads still blocks the save, even though the field started editable', function () {
    $ticketType = TicketType::factory()->create(['total_quantity' => 50, 'available_quantity' => 50]);
    $staff = Staff::factory()->eventManager()->create();

    $component = Livewire::actingAs($staff, 'staff')->test(EditTicketType::class, ['record' => $ticketType->getKey()]);

    // Simulate a ticket sale recorded between form load and save.
    $ticketType->update(['available_quantity' => 49]);

    $component->fillForm(['total_quantity' => 999])->call('save');

    expect($ticketType->fresh())
        ->total_quantity->toBe(50)
        ->available_quantity->toBe(49);
});

it('never lets available_quantity be directly edited', function () {
    $ticketType = TicketType::factory()->create(['total_quantity' => 50, 'available_quantity' => 50]);
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(EditTicketType::class, ['record' => $ticketType->getKey()])
        ->fillForm(['available_quantity' => 5])
        ->call('save');

    expect($ticketType->fresh()->available_quantity)->toBe(50);
});

it('refuses support and gate_operator from viewing, creating, or editing ticket types', function () {
    $ticketType = TicketType::factory()->create();

    foreach (['support', 'gateOperator'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();

        $this->actingAs($staff, 'staff')->get('/admin/ticket-types')->assertForbidden();
        $this->actingAs($staff, 'staff')->get('/admin/ticket-types/create')->assertForbidden();
        $this->actingAs($staff, 'staff')->get("/admin/ticket-types/{$ticketType->id}/edit")->assertForbidden();
    }
});

it('refuses event_manager from deleting a ticket type; only super_admin can', function () {
    $eventManager = Staff::factory()->eventManager()->create();
    $superAdmin = Staff::factory()->superAdmin()->create();
    $ticketType = TicketType::factory()->create();

    expect($eventManager->can('delete', $ticketType))->toBeFalse();
    expect($superAdmin->can('delete', $ticketType))->toBeTrue();
});
