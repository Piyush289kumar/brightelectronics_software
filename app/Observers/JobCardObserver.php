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

        // Do not update target when Job Card is created.
        // Target is counted only when it becomes Delivered.
    }

    public function updated(JobCard $jobCard): void
    {
        $this->syncLedger($jobCard);

        /*
        |--------------------------------------------------------------------------
        | 1. Job Card becomes Delivered
        |--------------------------------------------------------------------------
        |
        | This works regardless of who changes the status:
        | Admin / Manager / Store Manager / Team Leader / Team Lead
        |
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
        | 2. Amount changed after already Delivered
        |--------------------------------------------------------------------------
        |
        | Example:
        | ₹10,000 → ₹12,000 = +₹2,000
        | ₹10,000 → ₹8,000  = -₹2,000
        |
        */
        if (
            $jobCard->status === 'Delivered' &&
            $jobCard->wasChanged('amount')
        ) {
            $oldAmount = (float) $jobCard->getOriginal('amount');
            $newAmount = (float) $jobCard->amount;

            $difference = $newAmount - $oldAmount;

            if ($difference != 0) {
                $this->addAmountToUserTargets(
                    $jobCard,
                    $difference
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LEDGER SYNC
    |--------------------------------------------------------------------------
    */

    protected function syncLedger(JobCard $jobCard): void
    {
        $account = Account::first();

        if (!$account) {
            return;
        }

        $storeId = $jobCard->complain?->store_id;

        if (!$storeId) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Advance
        |--------------------------------------------------------------------------
        */

        if ((float) $jobCard->advance_amount > 0) {

            Ledger::updateOrCreate(
                [
                    'job_card_id' => $jobCard->id,
                    'narration' => 'Advance - Job Card #' . $jobCard->job_id,
                ],
                [
                    'account_id' => $account->id,
                    'store_id' => $storeId,
                    'complain_id' => $jobCard->complain_id,
                    'date' => now(),
                    'transaction_type' => 'credit',
                    'amount' => (float) $jobCard->advance_amount,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delivery
        |--------------------------------------------------------------------------
        */

        if ((float) $jobCard->on_delivery_amount > 0) {

            Ledger::updateOrCreate(
                [
                    'job_card_id' => $jobCard->id,
                    'narration' => 'Delivery - Job Card #' . $jobCard->job_id,
                ],
                [
                    'account_id' => $account->id,
                    'store_id' => $storeId,
                    'complain_id' => $jobCard->complain_id,
                    'date' => now(),
                    'transaction_type' => 'credit',
                    'amount' => (float) $jobCard->on_delivery_amount,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ASSIGNED ENGINEER / MACHINE MEN TARGET
    |--------------------------------------------------------------------------
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

        /*
        |--------------------------------------------------------------------------
        | Make sure JSON values are IDs
        |--------------------------------------------------------------------------
        */

        $engineers = collect($engineers)
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($engineers->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($engineers, $complain, $amount) {

            foreach ($engineers as $engineerId) {

                /*
                |--------------------------------------------------------------------------
                | Find current month's target for this assigned engineer
                |--------------------------------------------------------------------------
                */

                $target = UserTarget::query()
                    ->where('user_id', $engineerId)
                    ->whereHas('storeTarget', function ($q) use ($complain) {

                        $q->where('store_id', $complain->store_id)
                            ->where('year', now()->year)
                            ->where('month', now()->month);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$target) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Increase / decrease achieved amount
                |--------------------------------------------------------------------------
                */

                $newAchieved = (float) $target->achieved_amount + $amount;

                $target->achieved_amount = max(
                    0,
                    round($newAchieved, 2)
                );

                /*
                |--------------------------------------------------------------------------
                | Remaining target
                |--------------------------------------------------------------------------
                */

                $target->remaining_amount = max(
                    0,
                    round(
                        (float) $target->assigned_amount
                        - (float) $target->achieved_amount,
                        2
                    )
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