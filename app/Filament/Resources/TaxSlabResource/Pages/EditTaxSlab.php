<?php

namespace App\Filament\Resources\TaxSlabResource\Pages;

use App\Filament\Resources\TaxSlabResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxSlab extends EditRecord
{
    protected static string $resource = TaxSlabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
