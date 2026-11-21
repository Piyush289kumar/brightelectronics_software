<?php

namespace App\Filament\Resources\StoreInventoryInResource\Pages;

use App\Filament\Resources\StoreInventoryInResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreInventoryIn extends CreateRecord
{
    protected static string $resource = StoreInventoryInResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['purchase_order_source_id'])) {
            $data['purchase_order_id'] = $data['purchase_order_source_id'];
        } elseif (!empty($data['purchase_invoice_source_id'])) {
            $data['purchase_order_id'] = $data['purchase_invoice_source_id'];
        }

        unset(
            $data['purchase_order_source_id'],
            $data['purchase_invoice_source_id']
        );

        return $data;
    }
}
