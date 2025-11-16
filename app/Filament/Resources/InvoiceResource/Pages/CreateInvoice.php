<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\StoreInventory;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // if (empty($data['invoice_number'])) {
        //     $data['invoice_number'] = 'INV-' . strtoupper(\Illuminate\Support\Str::random(8));
        // }

        // $data['created_by'] = auth()->id();

        // return $data;

        // Remove any random number generation here
        // Let the model handle sequential number generation safely
        unset($data['number']);

        // Set the logged-in user
        $data['created_by'] = auth()->id();

        return $data;
    }


    // 🔥🔥 STOCK DEDUCT LOGIC HERE 🔥🔥
    protected function afterCreate(): void
    {
        $invoice = $this->record;                       // Saved invoice

        foreach ($invoice->items as $item) {
            $productId = $item->product_id;
            $qty = $item->quantity ?? 0;

            if ($productId && $qty > 0) {
                // Deduct from store inventory
                StoreInventory::decreaseStock(
                    auth()->user()->store_id,          // Current user's store
                    $productId,
                    $qty
                );
            }
        }
    }



    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
