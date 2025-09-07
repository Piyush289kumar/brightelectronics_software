<?php

namespace App\Filament\Resources\StoreDemandItemResource\Pages;

use App\Filament\Resources\StoreDemandItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreDemandItems extends ListRecords
{
    protected static string $resource = StoreDemandItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
