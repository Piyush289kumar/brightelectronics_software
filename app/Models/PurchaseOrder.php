<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class PurchaseOrder extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'po_number',
        'vendor_id',
        'store_id',
        'created_by',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

}
