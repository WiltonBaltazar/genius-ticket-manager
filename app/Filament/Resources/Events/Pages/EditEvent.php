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
     * Filament-form-scoped only (not a model-level hook — see Event model /
     * research.md §3 for why): end_date is internal bookkeeping derived from
     * start_date, never staff-editable, and never exposed on this form.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['end_date'] = Carbon::parse($data['start_date'])->startOfDay();

        return $data;
    }

    // FR-024 (stale-record edit fails with a not-found error) needs no code
    // here: Livewire rehydrates the public $record model property fresh from
    // the database on every request and throws ModelNotFoundException on its
    // own if the row is gone, before any page method runs.
}
