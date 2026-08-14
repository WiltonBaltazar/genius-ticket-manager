<?php

namespace App\Filament\Pages;

use App\Enums\StaffRole;
use App\Models\PaymentSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.payment-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    // Super Admin only (004-attendee-checkout follow-up): these values feed
    // the public checkout's WhatsApp/bank-transfer payment step, so they're
    // account-wide payment configuration, not per-event operational data.
    public static function canAccess(): bool
    {
        return auth('staff')->user()?->role === StaffRole::SuperAdmin;
    }

    public function mount(): void
    {
        $this->form->fill(PaymentSetting::current()->only([
            'whatsapp_number',
            'bank_account_name',
            'bank_account_number',
            'bank_name',
            'bank_branch',
            'bank_transfer_instructions',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('whatsapp_number')
                    ->label('WhatsApp number')
                    ->helperText('International format, digits only (e.g. 258840000000) — used to build the wa.me checkout link.')
                    ->maxLength(20),
                TextInput::make('bank_account_name')
                    ->label('Account name')
                    ->maxLength(255),
                TextInput::make('bank_account_number')
                    ->label('Account number')
                    ->maxLength(255),
                TextInput::make('bank_name')
                    ->label('Bank name')
                    ->maxLength(255),
                TextInput::make('bank_branch')
                    ->label('Branch')
                    ->maxLength(255),
                Textarea::make('bank_transfer_instructions')
                    ->label('Bank transfer instructions')
                    ->helperText('Shown to attendees under the bank details on the payment step — e.g. where to send proof of payment.')
                    ->rows(3),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        PaymentSetting::current()->update($this->form->getState());

        Notification::make()
            ->title('Payment settings saved')
            ->success()
            ->send();
    }
}
