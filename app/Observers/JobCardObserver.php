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
        // Create ledger only after delivery
        if (
            $jobCard->status !== 'Delivered' ||
            empty($jobCard->on_delivery_amount)
        ) {
            return;
        }

        $account = Account::first();

        if (!$account) {
            return;
        }

        Ledger::updateOrCreate(
            [
                'job_card_id' => $jobCard->id,
            ],
            [
                'account_id' => $account->id,
                'store_id' => $jobCard->complain->store_id, // use complaint store
                'date' => now(),
                'transaction_type' => 'credit',
                'amount' => $jobCard->on_delivery_amount,
                'narration' => 'Job Card #' . $jobCard->job_id,
            ]
        );
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
