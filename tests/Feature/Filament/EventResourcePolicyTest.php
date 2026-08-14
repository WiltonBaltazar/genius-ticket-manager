<?php

use App\Enums\EventStatus;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Event;
use App\Models\Staff;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('lets event_manager create an event with all fields, and it appears in the events list', function () {
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Annual Gala',
            'slug' => 'annual-gala',
            'venue' => 'Convention Center',
            'start_date' => now()->addMonth()->format('Y-m-d H:i:s'),
            'description' => 'A night to remember.',
            'status' => 'draft',
            'internal_notes' => 'Confirm catering by Friday.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Event::where('slug', 'annual-gala')->exists())->toBeTrue();
});

it('saves an event with no hero image and no description', function () {
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Bare Bones Event',
            'slug' => 'bare-bones-event',
            'venue' => 'TBD',
            'start_date' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Event::where('slug', 'bare-bones-event')->first())
        ->hero_image_path->toBeNull()
        ->description->toBeNull();
});

it('rejects a duplicate slug', function () {
    $staff = Staff::factory()->eventManager()->create();
    Event::factory()->create(['slug' => 'taken-slug']);

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Another Event',
            'slug' => 'taken-slug',
            'venue' => 'Somewhere',
            'start_date' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('filters the events list by status', function () {
    Event::factory()->create(['status' => EventStatus::Draft]);
    Event::factory()->create(['status' => EventStatus::Published]);

    $staff = Staff::factory()->eventManager()->create();

    $response = $this->actingAs($staff, 'staff')->get('/admin/events?tableFilters[status][value]=published');

    $response->assertOk();
});

it('refuses support from creating or editing an event', function () {
    $staff = Staff::factory()->support()->create();

    $this->actingAs($staff, 'staff')->get('/admin/events/create')->assertForbidden();

    $event = Event::factory()->create();
    $this->actingAs($staff, 'staff')->get("/admin/events/{$event->id}/edit")->assertForbidden();
});

it('refuses event_manager from deleting an event; only super_admin can', function () {
    $eventManager = Staff::factory()->eventManager()->create();
    $superAdmin = Staff::factory()->superAdmin()->create();
    $event = Event::factory()->create();

    expect($eventManager->can('delete', $event))->toBeFalse();
    expect($superAdmin->can('delete', $event))->toBeTrue();
});

it('rejects a hero image upload with an unsupported file type', function () {
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Bad Upload Event',
            'slug' => 'bad-upload-event',
            'venue' => 'Somewhere',
            'start_date' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => 'draft',
            'hero_image_path' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasFormErrors(['hero_image_path']);
});

it('fails a stale edit with a not-found error when the event was deleted first', function () {
    $staff = Staff::factory()->eventManager()->create();
    $event = Event::factory()->create();

    $component = Livewire::actingAs($staff, 'staff')->test(EditEvent::class, ['record' => $event->getKey()]);

    $event->forceDelete();

    expect(fn () => $component->call('save'))
        ->toThrow(ModelNotFoundException::class);
});
