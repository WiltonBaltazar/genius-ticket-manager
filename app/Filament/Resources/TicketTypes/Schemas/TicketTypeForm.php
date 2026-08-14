<?php

namespace App\Filament\Resources\TicketTypes\Schemas;

use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TicketTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->label('Event')
                    ->options(fn () => Event::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3),
                TextInput::make('price')
                    ->label('Price (MZN)')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('total_quantity')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->required()
                    // Re-evaluated by Filament against the record's current (Livewire-
                    // rehydrated, i.e. fresh-from-DB) state on every request, including
                    // the save request itself — this is what closes the FR-013 race, not
                    // just a one-time check at initial page load.
                    ->disabled(fn (?\App\Models\TicketType $record) => $record && $record->available_quantity < $record->total_quantity),
                TextInput::make('available_quantity')
                    ->label('Available Quantity')
                    ->numeric()
                    ->integer()
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                DateTimePicker::make('sales_start_date')
                    ->label('Sales Start')
                    ->seconds(false),
                DateTimePicker::make('sales_end_date')
                    ->label('Sales End')
                    ->seconds(false)
                    ->after('sales_start_date'),
            ]);
    }
}
