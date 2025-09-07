<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class PurchaseRequisitionItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'purchase_requisition_id',
        'product_id',
        'vendor_id',
        'quantity',
        'approved_quantity',
        'purchase_price',
        'approved_price',
        'total_price',
        'uom',
        'note',
    ];

    public function requisition()
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
