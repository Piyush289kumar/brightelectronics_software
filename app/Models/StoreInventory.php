<?php

namespace App\Models;

use App\Notifications\StockLowNotification;
use App\Observers\StoreInventoryObserver;
use DB;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory;
use Spatie\Permission\Traits\HasRoles;

#[ObservedBy([StoreInventoryObserver::class])]
class StoreInventory extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_id',
        'product_id',
        'quantity',
        'avg_purchase_price',
        'avg_selling_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'avg_purchase_price' => 'decimal:2',
        'avg_selling_price' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::saved(function (StoreInventory $storeInventory) {
            $productId = $storeInventory->product_id;

            // Sum quantity of all stores for this product
            $totalQuantity = self::where('product_id', $productId)->sum('quantity');

            // Calculate average prices
            $avgPurchasePrice = self::where('product_id', $productId)->avg('avg_purchase_price') ?: 0;
            $avgSellingPrice = self::where('product_id', $productId)->avg('avg_selling_price') ?: 0;

            Inventory::updateOrCreate(
                ['product_id' => $productId],
                [
                    'total_quantity' => $totalQuantity,
                    'avg_purchase_price' => $avgPurchasePrice,
                    'avg_selling_price' => $avgSellingPrice,
                ]
            );

        });
    }


    /**
     * Safely increase stock.
     */
    public static function increaseStock(int $storeId, int $productId, int $quantity): bool
    {
        $storeInventory = self::firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $productId],
            ['quantity' => 0]
        );

        $storeInventory->quantity += $quantity;
        $storeInventory->save();

        return true;
    }


    /**
     * Safely decrease stock.
     */
    public static function decreaseStock(int $storeId, int $productId, int $quantity): bool
    {
        $storeInventory = self::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->first();

        if (!$storeInventory || $storeInventory->quantity < $quantity) {
            return false; // Not enough stock
        }

        $storeInventory->quantity -= $quantity;
        $storeInventory->save();

        return true;
    }
}
