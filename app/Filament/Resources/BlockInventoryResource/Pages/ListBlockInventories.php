<?php

namespace App\Filament\Resources\BlockInventoryResource\Pages;

use App\Filament\Resources\BlockInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlockInventories extends ListRecords
{
    protected static string $resource = BlockInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
