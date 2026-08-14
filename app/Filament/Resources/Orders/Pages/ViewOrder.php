<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    // No header actions: every order field is disabled/non-editable from
    // this panel (FR-016); no edit route exists for this resource.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
