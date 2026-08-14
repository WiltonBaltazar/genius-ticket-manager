<?php

namespace App\Filament\Resources\TicketTypes\Pages;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditTicketType extends EditRecord
{
    protected static string $resource = TicketTypeResource::class;

    // No delete/restore/force-delete header actions: no role, including
    // super_admin, can delete a ticket type through this panel (FR-011).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
