<?php
namespace App\Observers;

use App\Models\StoreInventory;
use App\Services\InventoryLogger;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class StoreInventoryObserver
{
    public function created(StoreInventory $inventory): void
    {
        $inventory->loadMissing(['store', 'product']);

        if ((int) $inventory->quantity > 0) {
            InventoryLogger::log([
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'type' => 'adjustment_in',
                'quantity' => $inventory->quantity,
                'remarks' => sprintf(
                    'Initial store stock created for "%s", Product "%s", with QTY: %d',
                    $inventory->store->name ?? 'Unknown Store',
                    $inventory->product->name ?? 'Unknown Product',
                    $inventory->quantity
                ),
            ]);
        }

        $this->checkLowStock($inventory);
    }

    public function updated(StoreInventory $inventory): void
    {
        $inventory->loadMissing(['store', 'product']);

        if ($inventory->wasChanged('quantity')) {
            $old = (int) $inventory->getOriginal('quantity');
            $new = (int) $inventory->quantity;
            $diff = $new - $old;

            if ($diff !== 0) {
                InventoryLogger::log([
                    'store_id' => $inventory->store_id,
                    'product_id' => $inventory->product_id,
                    'type' => $diff > 0 ? 'adjustment_in' : 'adjustment_out',
                    'quantity' => abs($diff),
                    'remarks' => sprintf(
                        'Store stock updated for "%s", Product "%s", old QTY: %d → new QTY: %d (change: %s%d)',
                        $inventory->store->name ?? 'Unknown Store',
                        $inventory->product->name ?? 'Unknown Product',
                        $old,
                        $new,
                        $diff > 0 ? '+' : '',
                        $diff
                    ),
                ]);
            }
        }

        $this->checkLowStock($inventory);
    }

    public function deleted(StoreInventory $inventory): void
    {
        $inventory->loadMissing(['store', 'product']);

        $qty = (int) $inventory->getOriginal('quantity');
        if ($qty > 0) {
            InventoryLogger::log([
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'type' => 'adjustment_out',
                'quantity' => $qty,
                'remarks' => sprintf(
                    'Store stock deleted for "%s", Product "%s" — remaining QTY: %d removed',
                    $inventory->store->name ?? 'Unknown Store',
                    $inventory->product->name ?? 'Unknown Product',
                    $qty
                ),
            ]);
        }
    }

    /**
     * Check low stock and notify all users via Filament notifications.
     */
    protected static array $notifiedThisRequest = [];

    protected function checkLowStock(StoreInventory $inventory)
    {
        $product = $inventory->product;
        $storeName = $inventory->store->name;

        if (!$product || $inventory->quantity > $product->min_stock) {
            return;
        }

        $key = "{$product->id}_{$inventory->store_id}";

        // Skip if already notified in this request
        if (isset(self::$notifiedThisRequest[$key])) {
            return;
        }

        // Check if a notification already exists in database
        $alreadyNotified = DB::table('notifications')
            ->where('type', \Filament\Notifications\DatabaseNotification::class)
            ->whereJsonContains('data->title', "Stock Low: {$product->name}")
            ->whereJsonContains('data->body', "store '{$storeName}'")
            ->exists();

        if (!$alreadyNotified) {
            $users = User::all()->filter(fn($user) => $user->isAdmin() || $user->isStoreManager());

            foreach ($users as $user) {
                Notification::make()
                    ->title("Stock Low: {$product->name}")
                    ->body("Stock: {$inventory->quantity} < Min: {$product->min_stock} in '{$storeName}'")
                    ->danger() // color for urgency
                    ->icon('heroicon-o-exclamation-triangle') // icon for visual cue
                    ->sendToDatabase($user);
            }

            // Mark as notified for this request
            self::$notifiedThisRequest[$key] = true;
        }
    }


}
