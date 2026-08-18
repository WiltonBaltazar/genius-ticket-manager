<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, callable $set) => $operation === 'create'
                        ? $set('slug', Str::slug($state))
                        : null),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('venue')
                    ->label('Location')
                    ->maxLength(255),
                DateTimePicker::make('start_date')
                    ->label('Start Date & Time')
                    ->required()
                    ->seconds(false),
                DatePicker::make('end_date')
                    ->label('End Date')
                    ->afterOrEqual('start_date')
                    ->helperText('Leave blank for a single-day event.'),
                FileUpload::make('hero_image_path')
                    ->label('Hero Image')
                    ->image()
                    ->disk('public')
                    ->directory('events/hero-images')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120),
                RichEditor::make('description')
                    ->dehydrateStateUsing(fn (?string $state) => filled(strip_tags($state ?? '')) ? $state : null),
                Select::make('status')
                    ->options(collect(EventStatus::cases())->mapWithKeys(
                        fn (EventStatus $status) => [$status->value => ucfirst($status->value)]
                    ))
                    ->required(),
                Textarea::make('internal_notes')
                    ->label('Internal Notes')
                    ->rows(3),
            ]);
    }
}
