<?php

namespace App\Filament\Resources\Events\Tables;

use App\Enums\EventStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('venue')
                    ->label('Location')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Date & Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EventStatus $state) => match ($state) {
                        EventStatus::Draft => 'gray',
                        EventStatus::Published => 'success',
                        EventStatus::Closed => 'warning',
                        EventStatus::Archived => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(EventStatus::cases())->mapWithKeys(
                        fn (EventStatus $status) => [$status->value => ucfirst($status->value)]
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Unlike the row-level DeleteAction above (which Filament auto-gates against
                    // EventPolicy::delete() as a resource-standard action), DeleteBulkAction has no
                    // automatic per-record authorization — without this call it's selectable and
                    // runnable by any role, silently bypassing "only super_admin can delete an event".
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
