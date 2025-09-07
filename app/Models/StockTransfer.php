<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class StockTransfer extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'from_store_id',
        'to_store_id',
        'product_id',
        'quantity',
        'status',
        'requested_by',
        'approved_by',
        'remarks',
    ];

    protected static function booted()
    {
        static::updated(function (StockTransfer $transfer) {
            // Only adjust stock if status changed to 'approved' AND approved_by is set
            if (
                $transfer->isDirty('status')
                && $transfer->status === 'approved'
                && $transfer->approved_by
            ) {

                \DB::transaction(function () use ($transfer) {
                    // Decrement from-store
                    StoreInventory::decreaseStock(
                        $transfer->from_store_id,
                        $transfer->product_id,
                        $transfer->quantity
                    );

                    // Increment to-store
                    StoreInventory::increaseStock(
                        $transfer->to_store_id,
                        $transfer->product_id,
                        $transfer->quantity
                    );
                });
            }
        });
    }



    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
