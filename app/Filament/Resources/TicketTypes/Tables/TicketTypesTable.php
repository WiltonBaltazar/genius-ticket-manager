<?php

namespace App\Filament\Resources\TicketTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price (MZN)')
                    ->money('MZN')
                    ->sortable(),
                TextColumn::make('total_quantity')
                    ->sortable(),
                TextColumn::make('available_quantity')
                    ->sortable(),
                TextColumn::make('sales_start_date')
                    ->label('Sales Start')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sales_end_date')
                    ->label('Sales End')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // authorizeIndividualRecords(): DeleteBulkAction has no automatic
                    // per-record authorization otherwise — see EventsTable's identical
                    // note. Without it, bulk delete would bypass "only super_admin".
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
