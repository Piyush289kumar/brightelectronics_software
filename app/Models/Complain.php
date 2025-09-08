<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Complain extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = [
        'name',
        'mobile',
        'customer_email',
        'address',
        'google_map_location',
        'lead_source_id',
        'complain_id',
        'device',
        'size',
        'service_type',
        'first_action_code',
        'rsd_time',
        'cancel_reason',
        'status',
        'pon',
        'estimate_repair_amount',
        'estimate_new_amount',
        'assigned_by',
        'assigned_engineers',
    ];

    protected $casts = [
        'product_id' => 'array',
        'size' => 'array',
        'service_type' => 'array',
        'assigned_engineers' => 'array',
    ];

    public function leadSource()
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function jobCard()
    {
        return $this->hasOne(JobCard::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($complain) {
            if (empty($complain->complain_id)) {
                $complain->complain_id = self::generateComplainId($complain->name, $complain->mobile);
            }
        });
    }

    public static function generateComplainId($name, $mobile = null): string
    {
        $datePart = now()->format('md');
        $namePart = Str::of($name)->trim()->upper()->split('/\s+/')->map(fn($part) => $part)->flatten();

        if ($namePart->count() >= 2) {
            $letters = substr($namePart->first(), 0, 1) . substr($namePart->last(), 0, 1);
        } else {
            $letters = substr($namePart->first(), 0, 2);
        }

        $phonePart = substr($mobile ?? '0000000000', -4);

        return "{$datePart}{$letters}{$phonePart}";
    }
}
