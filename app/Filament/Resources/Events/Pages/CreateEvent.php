<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Filament-form-scoped only (not a model-level hook — see Event model /
     * research.md §3 for why): end_date is internal bookkeeping derived from
     * start_date, never staff-editable, and never exposed on this form.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['end_date'] = Carbon::parse($data['start_date'])->startOfDay();

        return $data;
    }
}
