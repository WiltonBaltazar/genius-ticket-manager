<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * end_date is staff-editable (multi-day events) but optional on the form;
     * defaults to a single-day event (same calendar date as start_date) when
     * left blank, since the DB column is NOT NULL.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['end_date'] ??= Carbon::parse($data['start_date'])->startOfDay();

        return $data;
    }

    // FR-024 (stale-record edit fails with a not-found error) needs no code
    // here: Livewire rehydrates the public $record model property fresh from
    // the database on every request and throws ModelNotFoundException on its
    // own if the row is gone, before any page method runs.
}
