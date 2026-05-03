<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class SiteInventoryIssueItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'site_inventory_issue_id',
        'product_id',
        'quantity',
        'return_qty',
        'notes',
    ];

    protected $old_return_qty = 0;

    // =============================
    // RELATIONS
    // =============================
    public function issue()
    {
        return $this->belongsTo(SiteInventoryIssue::class, 'site_inventory_issue_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // =============================
    // MODEL EVENTS
    // =============================
    protected static function booted()
    {
        /**
         * =============================
         * ✅ ON CREATE (ISSUE STOCK)
         * =============================
         */
        static::created(function (SiteInventoryIssueItem $item) {

            $issue = $item->issue;

            if (!$issue) return;

            // ✅ Decrease stock
            \App\Models\StoreInventory::decreaseStock(
                $issue->store_id,
                $item->product_id,
                $item->quantity
            );

            // ✅ Sync to JobCard
            if (!$issue->job_card_id) return;

            $jobCard = \App\Models\JobCard::find($issue->job_card_id);
            if (!$jobCard) return;

            $existing = collect($jobCard->product_id ?? []);

            $found = false;

            $updated = $existing->map(function ($row) use ($item, &$found) {

                if ($row['product_id'] == $item->product_id) {
                    $found = true;

                    return [
                        'product_id' => $row['product_id'],
                        'qty' => $row['qty'] + $item->quantity,
                    ];
                }

                return $row;
            });

            if (!$found) {
                $updated->push([
                    'product_id' => $item->product_id,
                    'qty' => $item->quantity,
                ]);
            }

            $jobCard->updateQuietly([
                'product_id' => $updated->values()->toArray()
            ]);
        });

        /**
         * =============================
         * 🔥 STORE OLD RETURN VALUE
         * =============================
         */
        static::updating(function (SiteInventoryIssueItem $item) {
            $item->old_return_qty = $item->getOriginal('return_qty') ?? 0;
        });

        /**
         * =============================
         * 🔥 RETURN LOGIC (FIXED)
         * =============================
         */
        static::updated(function (SiteInventoryIssueItem $item) {

            $issue = $item->issue;

            if (!$issue || !$issue->job_card_id) return;

            $old = $item->old_return_qty ?? 0;
            $new = $item->return_qty ?? 0;

            // 🚫 Prevent over return
            if ($new > $item->quantity) {
                return;
            }

            // 🔥 Calculate difference
            $diff = $new - $old;

            if ($diff <= 0) return;

            // =============================
            // ✅ ADD STOCK BACK
            // =============================
            \App\Models\StoreInventory::increaseStock(
                $issue->store_id,
                $item->product_id,
                $diff
            );

            // =============================
            // ✅ REDUCE FROM JOBCARD
            // =============================
            $jobCard = \App\Models\JobCard::find($issue->job_card_id);
            if (!$jobCard) return;

            $updated = collect($jobCard->product_id ?? [])
                ->map(function ($row) use ($item, $diff) {

                    if ($row['product_id'] == $item->product_id) {
                        return [
                            'product_id' => $row['product_id'],
                            'qty' => max($row['qty'] - $diff, 0),
                        ];
                    }

                    return $row;
                })
                ->filter(fn($row) => $row['qty'] > 0)
                ->values()
                ->toArray();

            $jobCard->updateQuietly([
                'product_id' => $updated
            ]);
        });
    }
}