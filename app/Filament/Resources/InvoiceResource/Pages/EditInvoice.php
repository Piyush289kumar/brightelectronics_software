<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\StoreInventory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected array $oldItems = [];

    public function mount($record): void
    {
        parent::mount($record);

        // Store OLD items before editing
        $this->oldItems = $this->record->items->map(fn($i) => [
            'product_id' => $i->product_id,
            'quantity' => $i->quantity,
        ])->toArray();
    }

    protected function afterSave(): void
    {
        static $done = false;
        if ($done)
            return;
        $done = true;

        $storeId = auth()->user()->store_id;

        // Step 1: OLD items
        $old = collect($this->oldItems)
            ->groupBy('product_id')
            ->map(fn($x) => $x->sum('quantity'));

        // 🟢 Step 2: REFRESH UPDATED invoice items (important fix!!)
        $this->record->load('items');

        // Step 3: NEW items
        $new = $this->record->items
            ->groupBy('product_id')
            ->map(fn($x) => $x->sum('quantity'));

        // Step 4: All involved product IDs
        $products = $old->keys()->merge($new->keys())->unique();

        foreach ($products as $productId) {

            $oldQty = $old[$productId] ?? 0;
            $newQty = $new[$productId] ?? 0;

            // ➤ Restore stock if quantity reduced
            if ($newQty < $oldQty) {
                StoreInventory::increaseStock($storeId, $productId, $oldQty - $newQty);
            }

            // ➤ Deduct stock if quantity increased
            if ($newQty > $oldQty) {
                StoreInventory::decreaseStock($storeId, $productId, $newQty - $oldQty);
            }
        }
    }



    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
