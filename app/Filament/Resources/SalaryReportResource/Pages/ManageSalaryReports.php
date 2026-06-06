<?php

namespace App\Filament\Resources\SalaryReportResource\Pages;

use App\Filament\Resources\SalaryReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSalaryReports extends ManageRecords
{
    protected static string $resource = SalaryReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
