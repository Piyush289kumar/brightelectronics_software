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
        $this->updateUserTarget($jobCard);
    }

    public function updated(JobCard $jobCard): void
    {
        $this->syncLedger($jobCard);
        $this->updateUserTarget($jobCard);
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


    protected function updateUserTarget(JobCard $jobCard): void
    {
        $engineers = $jobCard->complain?->assigned_engineers ?? [];

        if (empty($engineers)) {
            return;
        }

        foreach ($engineers as $engineerId) {

            $target = \App\Models\UserTarget::where('user_id', $engineerId)
                ->whereHas('storeTarget', function ($q) use ($jobCard) {
                    $q->where('month', now()->month)
                        ->where('year', now()->year)
                        ->where('store_id', $jobCard->complain->store_id);
                })
                ->first();

            if (!$target) {
                continue;
            }

            $collection = (float) $jobCard->amount;

            $target->achieved_amount += $collection;

            $target->remaining_amount = max(
                $target->assigned_amount - $target->achieved_amount,
                0
            );

            $target->save();
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
