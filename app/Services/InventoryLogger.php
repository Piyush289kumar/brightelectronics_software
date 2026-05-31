<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class InventoryLogger
{
    public static function log(array $data): ?InventoryMovement
    {
        $productId = $data['product_id'] ?? null;

        if (
            empty($productId) ||
            !Product::where('id', $productId)->exists()
        ) {
            logger()->error('Invalid product for inventory movement', [
                'product_id' => $productId,
                'data' => $data,
            ]);

            return null;
        }

        return InventoryMovement::create([
            'store_id' => $data['store_id'] ?? null,
            'product_id' => $data['product_id'],
            'user_id' => $data['user_id'] ?? Auth::id(),
            'type' => $data['type'],              // purchase, sale, transfer_in/out, adjustment_in/out, store_demand, store_demand_approved
            'quantity' => abs((int) $data['quantity']),
            'price' => $data['price'] ?? 0,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);
    }
}
