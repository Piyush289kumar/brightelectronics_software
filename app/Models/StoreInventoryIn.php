<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\StoreInventory;
use Spatie\Permission\Traits\HasRoles;

class StoreInventoryIn extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_id',
        'received_by',
        'received_by_text',
        'vendor_id',
        'invoice_no',
        'invoice_date',
        'vehicle_no',
        'driver_name',
        'driver_contact',
        'delivery_person',
        'delivery_info',
        'grand_total',
        'payment_status',
        'payment_method',
        'payment_date',
        'documents',
        'notes',
        'transaction_type',
    ];

    protected $casts = [
        'documents' => 'array',
        'invoice_date' => 'date',
        'payment_date' => 'date',
        'grand_total' => 'decimal:2',
    ];

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(StoreInventoryInItem::class, 'store_inventory_in_id');
    }

    // -----------------------------
    // Auto-increase stock after creation
    // -----------------------------
    protected static function booted()
    {
        static::created(function (StoreInventoryIn $record) {
            foreach ($record->items as $item) {
                // Find or create inventory record for this store & product
                $storeInventory = StoreInventory::firstOrCreate(
                    [
                        'store_id' => $record->store_id,
                        'product_id' => $item->product_id,
                    ],
                    [
                        'quantity' => 0,
                    ]
                );

                // Increase quantity
                $storeInventory->quantity += $item->quantity;
                $storeInventory->save();
            }
        });
    }
}
