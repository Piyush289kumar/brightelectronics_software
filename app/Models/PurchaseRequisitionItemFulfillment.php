<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;


class PurchaseRequisitionItemFulfillment extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'purchase_requisition_item_id',
        'product_id',
        'quantity',        
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function requisitionItem()
    {
        return $this->belongsTo(
            PurchaseRequisitionItem::class,
            'purchase_requisition_item_id'
        );
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
