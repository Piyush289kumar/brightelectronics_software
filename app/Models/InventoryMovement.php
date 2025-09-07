<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

class InventoryMovement extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_id',
        'product_id',
        'user_id',
        'type',
        'quantity',
        'price',
        'reference_type',
        'reference_id',
        'remarks',
    ];

    // Relationships (optional, recommended)
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
