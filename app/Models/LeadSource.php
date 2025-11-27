<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class LeadSource extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'lead_name',
        'lead_email',
        'lead_phone_number',
        'lead_type',
        'lead_code',
        'account_holder_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'account_type',
        'branch_name',
        'lead_incentive',
        'campaign_name',
        'keyword',
        'landing_page_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'lead_status',
        'note',
        'other',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->lead_code)) {
                $model->lead_code = static::generateLeadCode();
            }
        });
    }

    public static function generateLeadCode()
    {
        $prefix = 'LD';
        $year = now()->format('y');
        $counter = static::where('lead_code', 'LIKE', $prefix . $year . '%')->count() + 1;

        return $prefix . $year . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }

    // Example: LD2510001, LD2510002, etc.
}
