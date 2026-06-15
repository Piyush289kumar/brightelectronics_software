<?php

namespace App\Filament\Resources\AttendanceReportResource\Pages;

use App\Filament\Resources\AttendanceReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceReports extends ListRecords
{
    protected static string $resource = AttendanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
