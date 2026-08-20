<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Orders\DeleteOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

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
                DeleteAction::make()
                    // Default DeleteAction just calls $record->delete() — using()
                    // routes it through DeleteOrderAction instead so its reserved/sold
                    // quantity comes back rather than staying phantom-sold (see that
                    // action's docblock for the paid-order ticket-voiding detail).
                    ->using(fn (Order $record, DeleteOrderAction $deleteOrderAction) => $deleteOrderAction->handle($record, auth('staff')->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // authorizeIndividualRecords(): see EventsTable's identical note —
                    // without it, bulk delete bypasses "only super_admin".
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords()
                        ->using(function (DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records, DeleteOrderAction $deleteOrderAction) {
                            $records->each(function (Order $record) use ($action, $deleteOrderAction) {
                                try {
                                    $deleteOrderAction->handle($record, auth('staff')->user()) || $action->reportBulkProcessingFailure();
                                } catch (\Throwable $exception) {
                                    $action->reportBulkProcessingFailure();

                                    report($exception);
                                }
                            });
                        }),
                ]),
            ]);
    }
}
