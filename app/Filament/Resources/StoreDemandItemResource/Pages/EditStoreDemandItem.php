<?php

namespace App\Filament\Resources\StoreDemandItemResource\Pages;

use App\Filament\Resources\StoreDemandItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreDemandItem extends EditRecord
{
    protected static string $resource = StoreDemandItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
