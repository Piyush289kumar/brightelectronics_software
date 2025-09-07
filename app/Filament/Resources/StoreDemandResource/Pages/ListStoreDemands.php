<?php

namespace App\Filament\Resources\StoreDemandResource\Pages;

use App\Filament\Resources\StoreDemandResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreDemands extends ListRecords
{
    protected static string $resource = StoreDemandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
