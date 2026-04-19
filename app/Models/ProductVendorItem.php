<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVendorItem extends Model
{
    protected $fillable = [
        'product_vendor_id',
        'product_id',
        'category_id',
        'sub_category_id',
        'last_purchase_price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVendor()
    {
        return $this->belongsTo(ProductVendor::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }
}
