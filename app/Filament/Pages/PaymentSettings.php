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
use Filament\Schemas\Components\Section;
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
            'bank_nib',
            'bank_name',
            'bank_branch',
            'bank_transfer_instructions',
            'emola_number',
            'emola_name',
            'mpesa_number',
            'mpesa_name',
            'mkesh_number',
            'mkesh_name',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('WhatsApp')
                    ->description('A chat channel to arrange payment — not a receiving account, so no separate "configured" check beyond the number itself.')
                    ->schema([
                        TextInput::make('whatsapp_number')
                            ->label('WhatsApp number')
                            ->helperText('International format, digits only (e.g. 258840000000) — used to build the wa.me checkout link.')
                            ->maxLength(20),
                    ]),
                Section::make('Bank transfer')
                    ->schema([
                        TextInput::make('bank_account_name')
                            ->label('Account name')
                            ->maxLength(255),
                        TextInput::make('bank_account_number')
                            ->label('Account number')
                            ->maxLength(255),
                        TextInput::make('bank_nib')
                            ->label('NIB')
                            ->helperText('Número de Identificação Bancária — shown to attendees alongside the account number.')
                            ->maxLength(30),
                        TextInput::make('bank_name')
                            ->label('Bank name')
                            ->maxLength(255),
                        TextInput::make('bank_branch')
                            ->label('Branch')
                            ->maxLength(255),
                        Textarea::make('bank_transfer_instructions')
                            ->label('Instructions')
                            ->helperText('Shown to attendees under the bank details — e.g. where to send proof of payment.')
                            ->rows(3),
                    ])
                    ->columns(2),
                Section::make('E-Mola')
                    ->description('Leave blank to hide this option from the attendee checkout entirely.')
                    ->schema([
                        TextInput::make('emola_number')
                            ->label('Number')
                            ->maxLength(20),
                        TextInput::make('emola_name')
                            ->label('Registered name')
                            ->helperText('Shown to attendees so they can confirm they\'re sending to the right account.')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('M-Pesa')
                    ->description('Leave blank to hide this option from the attendee checkout entirely.')
                    ->schema([
                        TextInput::make('mpesa_number')
                            ->label('Number')
                            ->maxLength(20),
                        TextInput::make('mpesa_name')
                            ->label('Registered name')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('M-Kesh')
                    ->description('Leave blank to hide this option from the attendee checkout entirely.')
                    ->schema([
                        TextInput::make('mkesh_number')
                            ->label('Number')
                            ->maxLength(20),
                        TextInput::make('mkesh_name')
                            ->label('Registered name')
                            ->maxLength(255),
                    ])
                    ->columns(2),
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
