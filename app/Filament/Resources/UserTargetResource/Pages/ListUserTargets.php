<?php

namespace App\Filament\Resources\UserTargetResource\Pages;

use App\Filament\Resources\UserTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserTargets extends ListRecords
{
    protected static string $resource = UserTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
