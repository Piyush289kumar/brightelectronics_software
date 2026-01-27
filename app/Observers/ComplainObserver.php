<?php

namespace App\Observers;

use App\Models\Complain;
use App\Models\JobCard;

class ComplainObserver
{
    /**
     * Handle the Complain "created" event.
     */
    public function created(Complain $complain): void
    {
        $this->handleTimestamps($complain);
        $this->createJobCardIfPkd($complain);
    }

    /**
     * Handle the Complain "updated" event.
     */
    public function updated(Complain $complain): void
    {
        // Only create JobCard if first_action_code changed to PKD
        if ($complain->isDirty('first_action_code')) {
            $this->handleTimestamps($complain);
            $this->createJobCardIfPkd($complain);
        }
    }

    /**
     * Handle the Complain "deleted" event.
     */
    public function deleted(Complain $complain): void
    {
        //
    }

    /**
     * Handle the Complain "restored" event.
     */
    public function restored(Complain $complain): void
    {
        //
    }

    /**
     * Handle the Complain "force deleted" event.
     */
    public function forceDeleted(Complain $complain): void
    {
        //
    }
    protected function handleTimestamps(Complain $complain): void
    {
        $needsSave = false;

        if ($complain->first_action_code === 'PKD' && !$complain->pkd_time) {
            $complain->pkd_time = now();
            $needsSave = true;
        }

        if ($complain->first_action_code === 'Visit' && !$complain->visit_time) {
            $complain->visit_time = now();
            $needsSave = true;
        }

        if ($needsSave) {
            $complain->saveQuietly(); // Avoid triggering observer again
        }
    }


    /**
     * Helper method to create JobCard if PKD and not already exists
     */
    protected function createJobCardIfPkd(Complain $complain): void
    {
        if ($complain->first_action_code === 'PKD' && !$complain->jobCard) {
            // Get last job id number
            $lastJob = JobCard::latest('id')->first();
            $lastNumber = $lastJob ? (int) str_replace('JOB-', '', $lastJob->job_id) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT); // 5 digits, leading zeros

            JobCard::create([
                'complain_id' => $complain->id,
                'job_id' => 'JOB-' . $newNumber,
                'status' => 'Pending',
                'amount' => 0,
                'gst_amount' => 0,
                'expense' => 0,
                'gross_amount' => 0,
                'incentive_type' => null,
                'incentive_amount' => 0,
                'net_profit' => 0,
                'lead_incentive_amount' => 0,
                'bright_electronics_profit' => 0,
                'job_verified_by_admin' => false,
                'note' => 'Auto-generated job card for PKD action',
            ]);
        }
    }
}
