<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\JobCard;
use App\Models\Ledger;
use App\Models\UserTarget;
use Illuminate\Support\Facades\DB;

class JobCardObserver
{
    public function created(JobCard $jobCard): void
    {
        $this->syncLedger($jobCard);

        // Do NOT update target when creating.
        // Target is updated only when status becomes Delivered.
    }

    public function updated(JobCard $jobCard): void
    {
        $this->syncLedger($jobCard);

        /*
        |--------------------------------------------------------------------------
        | 1. Job Card becomes Delivered
        |--------------------------------------------------------------------------
        */
        if (
            $jobCard->status === 'Delivered' &&
            $jobCard->wasChanged('status')
        ) {
            $this->addAmountToUserTargets(
                $jobCard,
                (float) $jobCard->amount
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Amount changed AFTER already Delivered
        |--------------------------------------------------------------------------
        */
        if (
            $jobCard->status === 'Delivered' &&
            $jobCard->wasChanged('amount')
        ) {
            $oldAmount = (float) $jobCard->getOriginal('amount');
            $newAmount = (float) $jobCard->amount;

            $difference = $newAmount - $oldAmount;

            /*
             * Example:
             *
             * 10000 → 12000
             * difference = +2000
             *
             * 10000 → 8000
             * difference = -2000
             */

            if ($difference != 0) {
                $this->addAmountToUserTargets(
                    $jobCard,
                    $difference
                );
            }
        }
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
                    'store_id' => $jobCard->complain?->store_id,
                    'date' => now(),
                    'transaction_type' => 'credit',
                    'amount' => $jobCard->advance_amount,
                ]
            );
        }

        // ===============================
        // Delivery Entry
        // ===============================
        if ($jobCard->on_delivery_amount > 0) {

            Ledger::updateOrCreate(
                [
                    'job_card_id' => $jobCard->id,
                    'narration' => 'Delivery - Job Card #' . $jobCard->job_id,
                ],
                [
                    'account_id' => $account->id,
                    'store_id' => $jobCard->complain?->store_id,
                    'date' => now(),
                    'transaction_type' => 'credit',
                    'amount' => $jobCard->on_delivery_amount,
                ]
            );
        }
    }

    /**
     * Add/subtract amount from all assigned engineer targets.
     *
     * Positive amount = increase achieved
     * Negative amount = decrease achieved
     */
    protected function addAmountToUserTargets(
        JobCard $jobCard,
        float $amount
    ): void {

        $complain = $jobCard->complain;

        if (!$complain) {
            return;
        }

        $engineers = $complain->assigned_engineers ?? [];

        if (empty($engineers)) {
            return;
        }

        DB::transaction(function () use ($engineers, $complain, $amount) {

            foreach ($engineers as $engineerId) {

                $target = UserTarget::where('user_id', $engineerId)
                    ->whereHas('storeTarget', function ($q) use ($complain) {
                        $q->where('month', now()->month)
                            ->where('year', now()->year)
                            ->where('store_id', $complain->store_id);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$target) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Update Achieved
                |--------------------------------------------------------------------------
                */

                $target->achieved_amount = max(
                    0,
                    (float) $target->achieved_amount + $amount
                );

                /*
                |--------------------------------------------------------------------------
                | Update Remaining
                |--------------------------------------------------------------------------
                */

                $target->remaining_amount = max(
                    0,
                    (float) $target->assigned_amount
                    - (float) $target->achieved_amount
                );

                $target->save();
            }
        });
    }

    public function deleted(JobCard $jobCard): void
    {
        //
    }

    public function restored(JobCard $jobCard): void
    {
        //
    }

    public function forceDeleted(JobCard $jobCard): void
    {
        //
    }
}