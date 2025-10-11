<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class JobCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'complain_id',
        'job_id',
        'status',
        'amount',
        'gst_amount',
        'gross_amount',
        'incentive_type',
        'incentive_amount',
        'incentive_percentages', // json column for individual engineer %
        'net_profit',
        'lead_incentive_amount',
        'bright_electronics_profit',
        'job_verified_by_admin',
        'note',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'amount' => 'float',
        'gst_amount' => 'float',
        'incentive_amount' => 'float',
        'incentive_percentages' => 'array',
        'net_profit' => 'float',
        'lead_incentive_amount' => 'float',
        'bright_electronics_profit' => 'float',
    ];

    public function complain()
    {
        return $this->belongsTo(Complain::class, 'complain_id');
    }


    public function calculateAmounts()
    {
        // 18% GST
        $this->gst_amount = $this->amount * 0.18;
        $this->gross_amount = $this->amount + $this->gst_amount;

        // Staff Incentive (per engineer)
        $totalStaffIncentive = 0;
        if (is_array($this->incentive_percentages)) {
            foreach ($this->incentive_percentages as $percent) {
                $totalStaffIncentive += ($percent / 100) * $this->gross_amount;
            }
        }
        $this->incentive_amount = $totalStaffIncentive;

        // Lead incentive
        $leadPercentage = optional($this->complain?->leadSource)->lead_incentive ?? 0;
        $this->lead_incentive_amount = ($leadPercentage / 100) * $this->gross_amount;

        // Net profit
        $this->net_profit = $this->gross_amount - $this->incentive_amount - $this->lead_incentive_amount;

        // Remaining is Bright Electronics Profit
        $this->bright_electronics_profit = $this->net_profit;
    }
}
