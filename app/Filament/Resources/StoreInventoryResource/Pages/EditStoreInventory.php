<?php

namespace App\Filament\Resources\StoreInventoryResource\Pages;

use App\Filament\Resources\StoreInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreInventory extends EditRecord
{
    protected static string $resource = StoreInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
