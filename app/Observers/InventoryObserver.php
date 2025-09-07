<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Services\InventoryLogger;

class InventoryObserver
{
    public function created(Inventory $inventory): void
    {
        if (request()->get('skip_central_log')) {
            return;
        }

        if ((int) $inventory->total_quantity > 0) {
            InventoryLogger::log([
                'store_id'   => null,
                'product_id' => $inventory->product_id,
                'type'       => 'adjustment_in',
                'quantity'   => $inventory->total_quantity,
                'remarks'    => 'Initial central stock created',
            ]);
        }
    }

    public function updated(Inventory $inventory): void
    {
        if (request()->get('skip_central_log')) {
            return;
        }

        if ($inventory->wasChanged('total_quantity')) {
            $old = (int) $inventory->getOriginal('total_quantity');
            $new = (int) $inventory->total_quantity;
            $diff = $new - $old;

            if ($diff !== 0) {
                InventoryLogger::log([
                    'store_id'   => null,
                    'product_id' => $inventory->product_id,
                    'type'       => $diff > 0 ? 'adjustment_in' : 'adjustment_out',
                    'quantity'   => abs($diff),
                    'remarks'    => 'Central stock adjusted via update',
                ]);
            }
        }
    }

    public function deleted(Inventory $inventory): void
    {
        if (request()->get('skip_central_log')) {
            return;
        }

        $qty = (int) $inventory->getOriginal('total_quantity');
        if ($qty > 0) {
            InventoryLogger::log([
                'store_id'   => null,
                'product_id' => $inventory->product_id,
                'type'       => 'adjustment_out',
                'quantity'   => $qty,
                'remarks'    => 'Central stock record deleted; remaining qty removed',
            ]);
        }
    }
}
