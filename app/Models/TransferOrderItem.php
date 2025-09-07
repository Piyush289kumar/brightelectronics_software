<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class TransferOrderItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'transfer_order_id',
        'product_id',
        'quantity',
        'transferred_quantity',
    ];

    public function transferOrder()
    {
        return $this->belongsTo(TransferOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
