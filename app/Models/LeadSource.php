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
}
