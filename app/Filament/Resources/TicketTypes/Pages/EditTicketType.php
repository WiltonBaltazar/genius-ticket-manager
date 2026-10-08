<?php

namespace App\Filament\Resources\TicketTypes\Pages;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use App\Models\TicketType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditTicketType extends EditRecord
{
    protected static string $resource = TicketTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Resizing a lot moves available_quantity by the same amount, so seats
     * already taken stay taken. The row is locked and re-read so a checkout or
     * expiry landing after the form loaded is counted, and the version bump on
     * save makes any in-flight checkout UPDATE against the old row miss.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $fresh = TicketType::whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $record->setRawAttributes($fresh->getAttributes(), sync: true);

            if (array_key_exists('total_quantity', $data)) {
                $taken = $record->takenQuantity();

                if ((int) $data['total_quantity'] < $taken) {
                    throw ValidationException::withMessages([
                        'data.total_quantity' => "{$taken} tickets are already taken from this lot, so it can't go below {$taken}.",
                    ]);
                }

                $data['available_quantity'] = (int) $data['total_quantity'] - $taken;
            }

            $record->update($data);

            return $record;
        });
    }
}
