<?php

namespace App\Filament\Resources\ProductVendorResource\Pages;

use App\Filament\Resources\ProductVendorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductVendors extends ListRecords
{
    protected static string $resource = ProductVendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
