<?php

namespace App\Filament\Resources\StoreInventoryInResource\Pages;

use App\Filament\Resources\StoreInventoryInResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreInventoryIn extends EditRecord
{
    protected static string $resource = StoreInventoryInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
