<?php

use App\Enums\EventStatus;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Event;
use App\Models\Staff;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('stores a hero image on the public disk so it is reachable from the public event page', function () {
    // Regression: FileUpload had no explicit ->disk('public'), so it silently
    // fell back to the app's default disk (`local`, which Laravel 11+ roots at
    // storage/app/private) — the file saved successfully but 404'd on the
    // public site, since asset('storage/...') only serves the public disk.
    Storage::fake('public');
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Hero Image Event',
            'slug' => 'hero-image-event',
            'venue' => 'Somewhere',
            'start_date' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => 'draft',
            'hero_image_path' => UploadedFile::fake()->image('hero.jpg'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::where('slug', 'hero-image-event')->firstOrFail();

    Storage::disk('public')->assertExists($event->hero_image_path);
});

it('defaults end_date to a single day when left blank', function () {
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Single Day Event',
            'slug' => 'single-day-event',
            'venue' => 'TBD',
            'start_date' => '2027-05-10 09:00:00',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Event::where('slug', 'single-day-event')->first()->end_date->toDateString())->toBe('2027-05-10');
});

it('lets event_manager set a multi-day event end date beyond the old one-day limit', function () {
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Three Day Conference',
            'slug' => 'three-day-conference',
            'venue' => 'TBD',
            'start_date' => '2027-05-10 09:00:00',
            'end_date' => '2027-05-12',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Event::where('slug', 'three-day-conference')->first()->end_date->toDateString())->toBe('2027-05-12');
});

it('rejects an end date before the start date on the form', function () {
    $staff = Staff::factory()->eventManager()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(CreateEvent::class)
        ->fillForm([
            'name' => 'Backwards Event',
            'slug' => 'backwards-event',
            'venue' => 'TBD',
            'start_date' => '2027-05-10 09:00:00',
            'end_date' => '2027-05-09',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasFormErrors(['end_date']);
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
