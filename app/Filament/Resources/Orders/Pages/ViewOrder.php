<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    // Every order field stays disabled/non-editable from this panel
    // (FR-016; no edit route exists for this resource) — ConfirmPayment
    // (004-attendee-checkout) is the one narrow, distinct action this
    // resource exposes, not a general edit capability.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmPayment')
                ->label('Confirm Payment')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === OrderStatus::Pending
                    && auth('staff')->user()?->can('confirmPayment', $this->record))
                ->action(function (ConfirmOrderPaymentAction $action) {
                    $action->handle($this->record, auth('staff')->user());

                    Notification::make()
                        ->title('Payment confirmed')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
