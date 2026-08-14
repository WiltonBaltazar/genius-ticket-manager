<?php

use App\Enums\StaffRole;
use App\Models\Staff;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

it('redirects an unauthenticated request to /admin to the staff login page, not the attendee login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets a seeded staff member log in via /admin/login and land on the dashboard under the staff guard', function () {
    $staff = Staff::factory()->superAdmin()->create(['password' => 'Str0ng!Passw0rd']);

    Livewire::test(Login::class)
        ->set('data.email', $staff->email)
        ->set('data.password', 'Str0ng!Passw0rd')
        ->call('authenticate');

    $this->assertAuthenticatedAs($staff, 'staff');
});

it('shows super_admin the Events, Ticket Types, and Orders navigation entries', function () {
    $staff = Staff::factory()->superAdmin()->create();

    $response = $this->actingAs($staff, 'staff')->get('/admin');

    $response->assertOk();
    $response->assertSee('Events');
    $response->assertSee('Ticket Types');
    $response->assertSee('Orders');
});

it('shows gate_operator none of the Events, Ticket Types, or Orders navigation entries, and refuses direct access', function () {
    $staff = Staff::factory()->gateOperator()->create();

    $response = $this->actingAs($staff, 'staff')->get('/admin');
    $response->assertOk();
    $response->assertDontSee('Events');
    $response->assertDontSee('Ticket Types');
    $response->assertDontSee('Orders');

    $this->actingAs($staff, 'staff')->get('/admin/events')->assertForbidden();
    $this->actingAs($staff, 'staff')->get('/admin/ticket-types')->assertForbidden();
    $this->actingAs($staff, 'staff')->get('/admin/orders')->assertForbidden();
});

it('re-checks role on every request: a staff member demoted to gate_operator mid-session loses access on their very next request', function () {
    $staff = Staff::factory()->eventManager()->create();

    $this->actingAs($staff, 'staff')->get('/admin/events')->assertOk();

    $staff->update(['role' => StaffRole::GateOperator]);

    $this->actingAs($staff->fresh(), 'staff')->get('/admin/events')->assertForbidden();
});
