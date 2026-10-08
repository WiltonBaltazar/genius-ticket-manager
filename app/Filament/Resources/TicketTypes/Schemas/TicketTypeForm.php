<?php

namespace App\Filament\Resources\TicketTypes\Schemas;

use App\Models\Event;
use App\Models\TicketType;
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
                    ->minValue(fn (?TicketType $record) => $record?->takenQuantity() ?? 0)
                    ->required()
                    ->helperText(fn (?TicketType $record) => $record && ! $record->hasGoneLive()
                        ? 'This lot is not on sale yet — you can still increase or decrease it.'
                        : null)
                    // Re-evaluated by Filament against the record's current (Livewire-
                    // rehydrated, i.e. fresh-from-DB) state on every request, including
                    // the save request itself — this is what closes the FR-013 race, not
                    // just a one-time check at initial page load. A lot that hasn't gone
                    // live yet stays resizable; EditTicketType keeps it above seats taken.
                    ->disabled(fn (?TicketType $record) => $record
                        && $record->hasGoneLive()
                        && $record->available_quantity < $record->total_quantity),
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
