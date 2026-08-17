<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Actions\Orders\RefundOrderAction;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    // Every order field stays disabled/non-editable from this panel
    // (FR-016; no edit route exists for this resource) — ConfirmPayment and
    // Refund (004-attendee-checkout) are the two narrow, distinct actions
    // this resource exposes, not a general edit capability.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmPayment')
                ->label('Confirm Payment')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === OrderStatus::Pending
                    && auth('staff')->user()?->can('confirmPayment', $this->record))
                ->action(function (ConfirmOrderPaymentAction $confirmOrderPaymentAction) {
                    // Not named $action: Filament reserves that parameter name in an
                    // action closure to inject the Filament\Actions\Action component
                    // itself, regardless of the declared type hint — a real collision
                    // discovered only by clicking the button, not by any test, since
                    // the unit-level ConfirmOrderPaymentAction tests call the action
                    // class directly rather than through this closure.
                    $confirmOrderPaymentAction->handle($this->record, auth('staff')->user());

                    Notification::make()
                        ->title('Payment confirmed')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Action::make('refund')
                ->label('Refund')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This voids every ticket on the order and puts the seats back on sale. This cannot be undone.')
                ->visible(fn () => $this->record->status === OrderStatus::Paid
                    && auth('staff')->user()?->can('refund', $this->record))
                ->action(function (RefundOrderAction $refundOrderAction) {
                    $refundOrderAction->handle($this->record, auth('staff')->user());

                    Notification::make()
                        ->title('Order refunded')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
