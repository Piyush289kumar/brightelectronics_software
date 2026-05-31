<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\JobCard;
use App\Models\Ledger;

class JobCardObserver
{
    /**
     * Handle the JobCard "created" event.
     */
    public function created(JobCard $jobCard): void
    {
        //
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

    /**
     * Handle the JobCard "updated" event.
     */
    public function updated(JobCard $jobCard): void
    {

        if (
            $jobCard->status !== 'Delivered' ||
            empty($jobCard->amount)
        ) {
            return;
        }

        $account = Account::first();

        if (!$account) {
            logger('No account found');
            return;
        }

        Ledger::updateOrCreate(
            [
                'job_card_id' => $jobCard->id,
            ],
            [
                'account_id' => $account->id,
                'store_id' => 1,
                'date' => now(),
                'transaction_type' => 'credit',
                'amount' => $jobCard->amount,
                'narration' => 'Job Card #' . $jobCard->job_id,
            ]
        );

        logger('Ledger created', [
            'job_card_id' => $jobCard->id,
        ]);
    }
}
