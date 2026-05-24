<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Product;

class JobCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'complain_id',
        'job_id',
        'status',
        'amount',
        'gst_amount',
        'expense',
        'product_id',                // store selected product ids (json)
        'check_list',     // Multi Check Box Selection
        'gross_amount',
        'incentive_type',
        'incentive_amount',
        'incentive_percentages',     // json column for individual engineer %
        'net_profit',
        'lead_incentive_amount',
        'lead_incentive_percent',
        'bright_electronics_profit',
        'job_verified_by_admin',
        'payment_reference_number',
        'payment_reference_image_path',
        'spare_parts',
        'note',
    ];
    protected $casts = [
        'product_id' => 'array',
        'check_list' => 'array',
        'incentive_percentages' => 'array',
        'gross_amount' => 'float',
        'amount' => 'float',
        'gst_amount' => 'float',
        'expense' => 'float',
        'incentive_amount' => 'float',
        'net_profit' => 'float',
        'lead_incentive_amount' => 'float',
        'lead_incentive_percent' => 'float',
        'bright_electronics_profit' => 'float',
        'spare_parts' => 'array',
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


    public function recalculateFinancials()
    {
        $amount = (float) ($this->amount ?? 0);

        // ==================================
        // EXPENSE
        // ==================================
        $expense = 0;

        foreach (($this->spare_parts ?? []) as $part) {

            $product = Product::find($part['product_id'] ?? null);

            if (!$product) {
                continue;
            }

            $qty = (int) ($part['qty'] ?? 1);

            $expense += ($product->selling_price * $qty);
        }

        $expense = round($expense, 2);

        // ==================================
        // GST
        // ==================================
        $gstAmount = round(($amount * 18) / 100, 2);

        // ==================================
        // PROFIT BEFORE LEAD
        // ==================================
        $profit = $amount - $expense;

        // ==================================
        // LEAD INCENTIVE
        // ==================================
        $leadPercent = (float) (
            $this->complain?->leadSource?->lead_incentive ?? 0
        );

        $leadAmount = round(($profit * $leadPercent) / 100, 2);

        // ==================================
        // AFTER LEAD
        // ==================================
        $afterLeadProfit = $profit - $leadAmount;

        // ==================================
        // ENGINEER TOTAL
        // ==================================
        $totalEngineerAmount = 0;

        foreach (($this->incentive_percentages ?? []) as $engineer) {

            $percent = (float) ($engineer['percent'] ?? 0);

            $engAmount = round(($afterLeadProfit * $percent) / 100, 2);

            $totalEngineerAmount += $engAmount;
        }

        // ==================================
        // COMPANY PROFIT
        // ==================================
        $companyProfit = $afterLeadProfit - $totalEngineerAmount;

        // ==================================
        // SAVE VALUES
        // ==================================
        $this->expense = $expense;
        $this->gst_amount = $gstAmount;
        $this->gross_amount = $profit;
        $this->lead_incentive_percent = $leadPercent;
        $this->lead_incentive_amount = $leadAmount;
        $this->incentive_amount = $totalEngineerAmount;
        $this->bright_electronics_profit = $companyProfit;
    }
}
