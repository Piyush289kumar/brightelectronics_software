<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\JobCard;
use App\Models\Ledger;

class JobCardObserver
{

    public function created(JobCard $jobCard): void
    {
        $this->syncLedger($jobCard);
    }

    public function updated(JobCard $jobCard): void
    {
        // dd('Observer Updated', $jobCard->status, $jobCard->on_delivery_amount);
        $this->syncLedger($jobCard);
    }

    protected function syncLedger(JobCard $jobCard): void
    {
        $account = Account::first();

        if (!$account) {
            return;
        }

        // ===============================
        // Advance Entry
        // ===============================
        if ($jobCard->advance_amount > 0) {

            Ledger::updateOrCreate(
                [
                    'job_card_id' => $jobCard->id,
                    'narration' => 'Advance - Job Card #' . $jobCard->job_id,
                ],
                [
                    'account_id' => $account->id,
                    'store_id' => $jobCard->complain->store_id,
                    'date' => now(),
                    'transaction_type' => 'credit',
                    'amount' => $jobCard->advance_amount,
                ]
            );
        }

        // ===============================
        // Delivery Entry
        // ===============================
        if (
            $jobCard->status === 'Delivered' &&
            $jobCard->on_delivery_amount > 0
        ) {

            Ledger::updateOrCreate(
                [
                    'job_card_id' => $jobCard->id,
                    'narration' => 'Delivery - Job Card #' . $jobCard->job_id,
                ],
                [
                    'account_id' => $account->id,
                    'store_id' => $jobCard->complain->store_id,
                    'date' => now(),
                    'transaction_type' => 'credit',
                    'amount' => $jobCard->on_delivery_amount,
                ]
            );
        }
    }

    /**
     * Handle the JobCard "deleted" event.
     */
    public function deleted(JobCard $jobCard): void
    {
        //
    }

    /**
     * Handle the JobCard "restored" event.
     */
    public function restored(JobCard $jobCard): void
    {
        //
    }

    /**
     * Handle the JobCard "force deleted" event.
     */
    public function forceDeleted(JobCard $jobCard): void
    {
        //
    }

}
