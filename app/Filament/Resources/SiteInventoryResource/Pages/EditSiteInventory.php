<?php

namespace App\Filament\Resources\SiteInventoryResource\Pages;

use App\Filament\Resources\SiteInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiteInventory extends EditRecord
{
    protected static string $resource = SiteInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
