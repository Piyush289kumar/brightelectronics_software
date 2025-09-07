<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Spatie\Permission\Traits\HasRoles;

class SiteInventoryIssueItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'site_inventory_issue_id',
        'product_id',
        'quantity',
        'notes',
    ];

    protected $oldQuantity = null;

    public function issue()
    {
        return $this->belongsTo(SiteInventoryIssue::class, 'site_inventory_issue_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::created(function (SiteInventoryIssueItem $item) {
            // Get the parent issue
            $issue = $item->issue;

            // Decrement stock for this item only
            StoreInventory::decreaseStock(
                $issue->store_id,
                $item->product_id,
                $item->quantity
            );
        });
    }

}
