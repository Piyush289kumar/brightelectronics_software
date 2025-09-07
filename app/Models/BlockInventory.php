<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
class BlockInventory extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'block_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}