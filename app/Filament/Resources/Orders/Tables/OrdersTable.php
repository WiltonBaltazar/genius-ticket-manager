<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['attendee', 'orderItems.ticketType.event']))
            ->defaultSort('created_at', 'desc')
            // Read-mostly (FR-016): no edit and no create route exists for this
            // resource — delete is the one exception, restricted to super_admin
            // via OrderPolicy::delete().
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID')
                    ->searchable(),
                TextColumn::make('attendee.name')
                    ->label('Attendee')
                    ->searchable(),
                TextColumn::make('attendee.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->getStateUsing(fn (Order $record) => $record->event()?->name),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Pending => 'gray',
                        OrderStatus::Paid => 'success',
                        OrderStatus::Failed => 'danger',
                        OrderStatus::Refunded => 'warning',
                        OrderStatus::Cancelled => 'danger',
                        OrderStatus::Expired => 'danger',
                    }),
                TextColumn::make('total_amount')
                    ->label('Total (MZN)')
                    ->money('MZN'),
                TextColumn::make('payment_method'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // authorizeIndividualRecords(): see EventsTable's identical note —
                    // without it, bulk delete bypasses "only super_admin".
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
