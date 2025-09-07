<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class TransferOrder extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'transfer_number',
        'from_store_id',
        'to_store_id',
        'created_by',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(TransferOrderItem::class);
    }

    /**
     * Create Transfer Order from Purchase Requisition
     */

}
