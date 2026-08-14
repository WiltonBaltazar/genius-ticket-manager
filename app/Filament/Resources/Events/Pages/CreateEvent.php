<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /**
     * end_date is staff-editable (multi-day events) but optional on the form;
     * defaults to a single-day event (same calendar date as start_date) when
     * left blank, since the DB column is NOT NULL.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['end_date'] ??= Carbon::parse($data['start_date'])->startOfDay();

        return $data;
    }
}
