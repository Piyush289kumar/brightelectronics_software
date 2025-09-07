<?php

namespace App\Filament\Resources\SiteInventoryIssueResource\Pages;

use App\Filament\Resources\SiteInventoryIssueResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\StoreInventory;
use App\Models\SiteInventoryIssue;
use Filament\Notifications\Notification;

class CreateSiteInventoryIssue extends CreateRecord
{
    protected static string $resource = SiteInventoryIssueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Add store_id, site_id, issued_by to each repeater product item
        foreach ($data['products'] as &$product) {
            $product['store_id'] = $data['store_id'];
            $product['site_id'] = $data['site_id'];
            $product['issued_by'] = $data['issued_by'];

            // Validate stock
            $storeInventory = StoreInventory::where('store_id', $data['store_id'])
                ->where('product_id', $product['product_id'])
                ->first();

            if (!$storeInventory || $product['quantity'] > $storeInventory->quantity) {
                Notification::make()
                    ->title('Insufficient stock')
                    ->body('Store does not have enough quantity for ' . ($storeInventory?->product->name ?? 'this product') . '.')
                    ->danger()
                    ->send();

                throw new \Exception('Insufficient stock in store.');
            }

            // Reduce store stock immediately
            $storeInventory->quantity -= $product['quantity'];
            $storeInventory->save();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): SiteInventoryIssue
    {
        // Create one record for each product in repeater
        $records = [];
        foreach ($data['products'] as $productData) {
            $records[] = SiteInventoryIssue::create($productData);
        }

        // Return first record for Filament tracking
        return $records[0];
    }
}
