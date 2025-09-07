<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Inventory extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'product_id',
        'total_quantity',
        'avg_purchase_price',
        'avg_selling_price',
        'min_stock',
        'max_stock',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
        'total_quantity' => 'integer',
        'avg_purchase_price' => 'decimal:2',
        'avg_selling_price' => 'decimal:2',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
