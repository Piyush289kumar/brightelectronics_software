<?php

namespace App\Filament\Resources\SiteInventoryIssueResource\Pages;

use App\Filament\Resources\SiteInventoryIssueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiteInventoryIssues extends ListRecords
{
    protected static string $resource = SiteInventoryIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
