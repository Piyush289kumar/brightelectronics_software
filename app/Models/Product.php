<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Product extends Model
{
    use HasFactory, HasRoles;
    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'unit',
        'brand',
        'category_id',
        'hsn_code',
        'tax_slab_id',
        'gst_rate',
        'purchase_price',
        'selling_price',
        'mrp',
        'track_inventory',
        'min_stock',
        'max_stock',
        'image_path',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
        'gst_rate' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'mrp' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function taxSlab(): BelongsTo
    {
        return $this->belongsTo(TaxSlab::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(ProductVendor::class);
    }

    public function storeInventories()
    {
        return $this->hasMany(StoreInventory::class);
    }

    /**
     * Boot method to auto-generate SKU and barcode.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            // Generate SKU if empty
            if (empty($product->sku)) {
                $product->sku = static::generateUniqueSku($product->name);
            }

            // Generate barcode if empty
            if (empty($product->barcode)) {
                $product->barcode = static::generateUniqueBarcode();
            }
        });
    }

    protected static function generateUniqueSku($name)
    {
        do {
            // Example: PROD-XXXX
            $sku = strtoupper(Str::slug($name, '')) . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('sku', $sku)->exists());

        return $sku;
    }

    protected static function generateUniqueBarcode()
    {
        do {
            // 12-digit random barcode
            $barcode = strtoupper(Str::random(12));
        } while (static::where('barcode', $barcode)->exists());

        return $barcode;
    }
}
