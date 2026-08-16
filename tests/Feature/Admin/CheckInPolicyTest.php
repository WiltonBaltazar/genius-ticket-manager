<?php

use App\Models\Staff;
use App\Models\Ticket;

it('allows checkIn for super_admin, event_manager, and gate_operator', function () {
    foreach (['superAdmin', 'eventManager', 'gateOperator'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();
        expect($staff->can('checkIn', Ticket::class))->toBeTrue();
    }
});

it('refuses checkIn for support', function () {
    $staff = Staff::factory()->support()->create();

    expect($staff->can('checkIn', Ticket::class))->toBeFalse();
});

it('serves the check-in page to an authorized role', function () {
    $staff = Staff::factory()->gateOperator()->create();

    $this->actingAs($staff, 'staff')->get('/admin/check-in')->assertOk();
});

it('forbids the check-in page for an unauthorized role', function () {
    $staff = Staff::factory()->support()->create();

    $this->actingAs($staff, 'staff')->get('/admin/check-in')->assertForbidden();
});

it('redirects a guest to the staff login page instead of the attendee one', function () {
    $this->get('/admin/check-in')->assertRedirect('/admin/login');
});
