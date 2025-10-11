<?php

namespace App\Filament\Resources\StoreTargetResource\Pages;

use App\Filament\Resources\StoreTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreTargets extends ListRecords
{
    protected static string $resource = StoreTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
