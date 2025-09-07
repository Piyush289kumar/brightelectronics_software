<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class JobCard extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'complain_id',
        'job_id',
        'status',
        'amount',
        'gst_amount',
        'expense',
        'gross_amount',
        'incentive_type',
        'incentive_amount',
        'net_profit',
        'lead_incentive_amount',
        'bright_electronics_profit',
        'job_verified_by_admin',
        'note',
    ];

    public function complain()
    {
        return $this->belongsTo(Complain::class);
    }
}
