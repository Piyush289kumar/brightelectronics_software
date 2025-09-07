<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\StoreInventory;
use Spatie\Permission\Traits\HasRoles;

class StoreInventoryInItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_inventory_in_id',
        'product_id',
        'quantity',
        'note',
    ];

    public function inventoryIn()
    {
        return $this->belongsTo(StoreInventoryIn::class, 'store_inventory_in_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::created(function (StoreInventoryInItem $item) {
            $inventoryIn = $item->inventoryIn;

            // Increase stock for this store & product
            StoreInventory::increaseStock(
                $inventoryIn->store_id,
                $item->product_id,
                $item->quantity
            );
        });
    }
}
