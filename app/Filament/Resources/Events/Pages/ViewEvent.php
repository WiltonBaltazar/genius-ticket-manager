<?php

namespace App\Filament\Resources\Events\Pages;

use App\Actions\Events\ExportEventAttendeesAction;
use App\Filament\Resources\Events\EventResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportAttendees')
                ->label('Export Attendees')
                ->visible(fn () => auth('staff')->user()?->can('exportAttendees', $this->record))
                ->action(function (ExportEventAttendeesAction $exportEventAttendeesAction) {
                    $rows = $exportEventAttendeesAction->handle($this->record);
                    $columns = ['Name', 'Email', 'Phone', 'Ticket Type', 'Event Date', 'Status', 'Checked In At', 'Order Reference', 'Order Status'];
                    $fileName = "attendees-{$this->record->slug}-".now()->format('Y-m-d').'.xlsx';

                    // openspout, not phpoffice/phpspreadsheet: already a dependency (Filament's own
                    // XLSX export downloader uses it) and streams rather than building the whole
                    // workbook in memory — this stays synchronous, since this project has no
                    // confirmed persistent queue worker, so this can't be a queued Filament export.
                    return response()->streamDownload(function () use ($rows, $columns, $fileName) {
                        $writer = new Writer;
                        $writer->openToBrowser($fileName);
                        $writer->addRow(Row::fromValues($columns));

                        foreach ($rows as $row) {
                            $writer->addRow(Row::fromValues(array_values($row)));
                        }

                        $writer->close();
                    }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
                }),
            EditAction::make(),
        ];
    }
}
