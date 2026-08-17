<?php

use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\Staff;
use Livewire\Livewire;

it('lets super_admin bulk delete events', function () {
    $staff = Staff::factory()->superAdmin()->create();
    $events = Event::factory()->count(3)->create();

    Livewire::actingAs($staff, 'staff')
        ->test(ListEvents::class)
        ->callTableBulkAction('delete', $events);

    expect(Event::whereIn('id', $events->pluck('id'))->count())->toBe(0);
});

it('refuses event_manager\'s bulk delete attempt, since only super_admin can delete an event', function () {
    // Regression: DeleteBulkAction has no automatic per-record policy authorization the
    // way the row-level DeleteAction does — without ->authorizeIndividualRecords() on it,
    // this would silently succeed for event_manager despite EventPolicy::delete() denying it.
    $staff = Staff::factory()->eventManager()->create();
    $events = Event::factory()->count(2)->create();

    Livewire::actingAs($staff, 'staff')
        ->test(ListEvents::class)
        ->callTableBulkAction('delete', $events);

    expect(Event::whereIn('id', $events->pluck('id'))->count())->toBe(2);
});
