<?php

namespace App\Filament\Resources\StoreTargetResource\Pages;

use App\Filament\Resources\StoreTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreTarget extends EditRecord
{
    protected static string $resource = StoreTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
