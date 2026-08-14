<?php

namespace App\Filament\Resources\TicketTypes\Pages;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketType extends CreateRecord
{
    protected static string $resource = TicketTypeResource::class;

    /**
     * available_quantity is never staff-entered (FR-012); on create it starts
     * equal to total_quantity since no tickets have sold yet.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['available_quantity'] = $data['total_quantity'];

        return $data;
    }
}
