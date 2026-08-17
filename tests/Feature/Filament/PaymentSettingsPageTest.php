<?php

use App\Filament\Pages\PaymentSettings;
use App\Models\PaymentSetting;
use App\Models\Staff;
use Livewire\Livewire;

it('allows a super admin to view the payment settings page', function () {
    $staff = Staff::factory()->superAdmin()->create();

    $this->actingAs($staff, 'staff')
        ->get('/admin/payment-settings')
        ->assertOk();
});

it('refuses non-super-admin roles', function () {
    foreach (['eventManager', 'support', 'gateOperator'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();

        $this->actingAs($staff, 'staff')
            ->get('/admin/payment-settings')
            ->assertForbidden();
    }
});

it('saves updated payment settings and they take effect immediately', function () {
    $staff = Staff::factory()->superAdmin()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(PaymentSettings::class)
        ->fillForm([
            'whatsapp_number' => '258850001111',
            'bank_account_name' => 'Genius Behind the Brands',
            'bank_account_number' => '9999999',
            'bank_nib' => '0001 0002 12345678901 57',
            'bank_name' => 'Millennium BIM',
            'bank_branch' => 'Maputo Central',
            'bank_transfer_instructions' => 'Send proof of payment to payments@example.test or via WhatsApp.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = PaymentSetting::current();

    expect($settings->whatsapp_number)->toBe('258850001111');
    expect($settings->bank_nib)->toBe('0001 0002 12345678901 57');
    expect($settings->bank_transfer_instructions)->toBe('Send proof of payment to payments@example.test or via WhatsApp.');
});

it('exposes the current payment settings to the public checkout config', function () {
    PaymentSetting::current()->update([
        'whatsapp_number' => '258860002222',
        'bank_nib' => '0001 0002 12345678901 57',
        'bank_transfer_instructions' => 'Please email your receipt to finance@example.test.',
    ]);

    $response = $this->get('/auth/login');

    $response->assertOk();
    $response->assertSee('258860002222');
    $response->assertSee('0001 0002 12345678901 57');
    $response->assertSee('Please email your receipt to finance@example.test.', escape: false);
});
