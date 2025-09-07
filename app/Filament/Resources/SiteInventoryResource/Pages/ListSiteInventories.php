<?php

namespace App\Filament\Resources\SiteInventoryResource\Pages;

use App\Filament\Resources\SiteInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiteInventories extends ListRecords
{
    protected static string $resource = SiteInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
