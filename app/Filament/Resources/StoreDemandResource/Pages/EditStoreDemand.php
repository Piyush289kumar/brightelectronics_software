<?php

namespace App\Filament\Resources\StoreDemandResource\Pages;

use App\Filament\Resources\StoreDemandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreDemand extends EditRecord
{
    protected static string $resource = StoreDemandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
