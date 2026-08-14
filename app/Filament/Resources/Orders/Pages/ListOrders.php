<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // No header actions: orders are never created from this panel (FR-016).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
