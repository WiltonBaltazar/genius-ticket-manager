<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Enums\StaffRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (StaffRole $state) => Str::headline($state->value))
                    ->color(fn (StaffRole $state) => match ($state) {
                        StaffRole::SuperAdmin => 'danger',
                        StaffRole::EventManager => 'success',
                        StaffRole::Support => 'info',
                        StaffRole::GateOperator => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(collect(StaffRole::cases())->mapWithKeys(
                        fn (StaffRole $role) => [$role->value => Str::headline($role->value)]
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Same reasoning as EventsTable: DeleteBulkAction has no automatic
                    // per-record authorization, so this is needed even though only
                    // super_admin can reach this table at all (StaffPolicy::viewAny).
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
