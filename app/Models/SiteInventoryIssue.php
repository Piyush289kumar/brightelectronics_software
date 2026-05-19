<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class SiteInventoryIssue extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_id',
        'job_card_id',
        'site_id',
        'issued_by',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }

    // public function site()
    // {
    //     return $this->belongsTo(Site::class);
    // }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }


    public function items()
    {
        return $this->hasMany(SiteInventoryIssueItem::class);
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }


    protected static function booted()
    {
        static::saved(function ($issue) {

            if (!$issue->job_card_id) {
                return;
            }

            $jobCard = JobCard::find($issue->job_card_id);

            if (!$jobCard) {
                return;
            }

            // Convert stock issue items to spare_parts
            $spareParts = $issue->items->map(function ($item) {

                return [
                    'product_id' => $item->product_id,
                    'qty' => $item->quantity,
                ];

            })->values()->toArray();

            // Save into job card
            $existing = collect($jobCard->spare_parts ?? []);

            $new = collect($spareParts);

            $jobCard->spare_parts = $existing
                ->merge($new)
                ->values()
                ->toArray();

            // Recalculate
            $jobCard->recalculateFinancials();

            // Save
            $jobCard->saveQuietly();
        });
    }

}
