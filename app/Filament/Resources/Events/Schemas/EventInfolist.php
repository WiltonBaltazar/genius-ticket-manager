<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventStatus;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('venue')->label('Location'),
                TextEntry::make('start_date')->label('Date & Time')->dateTime(),
                ImageEntry::make('hero_image_path')->label('Hero Image'),
                TextEntry::make('description')->html(),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (EventStatus $state) => match ($state) {
                        EventStatus::Draft => 'gray',
                        EventStatus::Published => 'success',
                        EventStatus::Closed => 'warning',
                        EventStatus::Archived => 'danger',
                    }),
                TextEntry::make('internal_notes')->label('Internal Notes'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
