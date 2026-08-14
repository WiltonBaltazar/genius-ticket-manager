<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')->label('Order ID'),
                TextEntry::make('attendee.name')->label('Attendee'),
                TextEntry::make('attendee.email')->label('Email'),
                TextEntry::make('event')
                    ->label('Event')
                    ->state(fn (Order $record) => $record->event()?->name),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Pending => 'gray',
                        OrderStatus::Paid => 'success',
                        OrderStatus::Failed => 'danger',
                        OrderStatus::Refunded => 'warning',
                        OrderStatus::Cancelled => 'danger',
                        OrderStatus::Expired => 'danger',
                    }),
                TextEntry::make('total_amount')->label('Total (MZN)')->money('MZN'),
                TextEntry::make('payment_method'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('proof_of_payment_path')
                    ->label('Proof of Payment')
                    ->visible(fn (Order $record) => filled($record->proof_of_payment_path))
                    ->formatStateUsing(fn () => 'View uploaded file')
                    ->url(fn (Order $record) => route('admin.orders.proof-of-payment', $record))
                    ->openUrlInNewTab(),
                RepeatableEntry::make('orderItems')
                    ->label('Items')
                    ->schema([
                        TextEntry::make('ticketType.name')->label('Ticket Type'),
                        TextEntry::make('quantity'),
                        TextEntry::make('unit_price')->label('Unit Price (MZN)')->money('MZN'),
                    ])
                    ->columns(3),
            ]);
    }
}
