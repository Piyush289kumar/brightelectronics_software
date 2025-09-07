<?php

namespace App\Filament\Resources\BlockInventoryResource\Pages;

use App\Filament\Resources\BlockInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlockInventory extends EditRecord
{
    protected static string $resource = BlockInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
