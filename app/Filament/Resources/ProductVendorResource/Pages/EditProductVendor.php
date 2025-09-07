<?php

namespace App\Filament\Resources\ProductVendorResource\Pages;

use App\Filament\Resources\ProductVendorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductVendor extends EditRecord
{
    protected static string $resource = ProductVendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
