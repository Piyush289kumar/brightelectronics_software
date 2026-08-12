<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\JobCard;
use App\Models\Ledger;
use App\Models\UserTarget;
use Illuminate\Support\Facades\DB;

class JobCardObserver
{
    /*
    |--------------------------------------------------------------------------
    | CREATED
    |--------------------------------------------------------------------------
    */

    public function created(JobCard $jobCard): void
    {
        $this->syncLedger($jobCard);

        /*
        |--------------------------------------------------------------------------
        | If a Job Card is created directly as Delivered,
        | include it in the target.
        |--------------------------------------------------------------------------
        */

        if ($jobCard->status === 'Delivered') {
            $this->syncUserTargets($jobCard);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATED
    |--------------------------------------------------------------------------
    */

    public function updated(JobCard $jobCard): void
    {
        $this->syncLedger($jobCard);

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Recalculate target whenever:
        |
        | 1. Status changes
        | 2. Amount changes
        | 3. Complaint changes
        |
        | We do NOT add/subtract the difference anymore.
        |
        */

        if ($jobCard->wasChanged([
            'status',
            'amount',
            'complain_id',
        ])) {
            $this->syncUserTargets($jobCard);
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
        | ADVANCE
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
        | DELIVERY
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
    | SYNC USER TARGETS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | achieved_amount is calculated from the actual Delivered Job Cards.
    |
    | Example:
    |
    | Job Card = ₹5,000
    | ↓
    | Target achieved = ₹5,000
    |
    | Edit Job Card:
    | ₹5,000 → ₹4,500
    | ↓
    | Target achieved = ₹4,500
    |
    | Edit:
    | ₹4,500 → ₹6,000
    | ↓
    | Target achieved = ₹6,000
    |
    */

    protected function syncUserTargets(JobCard $jobCard): void
    {
        /*
        |--------------------------------------------------------------------------
        | Get complaint
        |--------------------------------------------------------------------------
        */

        $complain = $jobCard->complain;

        if (!$complain) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Get assigned engineers
        |--------------------------------------------------------------------------
        */

        $engineers = collect($complain->assigned_engineers ?? [])
            ->map(function ($engineer) {

                /*
                | Sometimes JSON can contain:
                |
                | 5
                | "5"
                | ["5"]
                |
                */

                if (is_array($engineer)) {
                    return (int) (
                        $engineer['id']
                        ?? $engineer['user_id']
                        ?? 0
                    );
                }

                return (int) $engineer;
            })
            ->filter()
            ->unique()
            ->values();


        if ($engineers->isEmpty()) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Store
        |--------------------------------------------------------------------------
        */

        $storeId = $complain->store_id;

        if (!$storeId) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Current month/year
        |--------------------------------------------------------------------------
        */

        $year = now()->year;
        $month = now()->month;


        DB::transaction(function () use (
            $engineers,
            $storeId,
            $year,
            $month
        ) {

            foreach ($engineers as $engineerId) {

                /*
                |--------------------------------------------------------------------------
                | Find user's target for current month
                |--------------------------------------------------------------------------
                */

                $target = UserTarget::query()
                    ->where('user_id', $engineerId)
                    ->whereHas('storeTarget', function ($query) use (
                        $storeId,
                        $year,
                        $month
                    ) {

                        $query
                            ->where('store_id', $storeId)
                            ->where('year', $year)
                            ->where('month', $month);
                    })
                    ->lockForUpdate()
                    ->first();


                /*
                |--------------------------------------------------------------------------
                | No target
                |--------------------------------------------------------------------------
                */

                if (!$target) {
                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Calculate REAL achieved amount
                |--------------------------------------------------------------------------
                |
                | Only Delivered Job Cards count.
                |
                | Do not use the existing achieved_amount.
                |
                */

                $achievedAmount = JobCard::query()
                    ->where('status', 'Delivered')
                    ->whereHas('complain', function ($query) use (
                        $storeId,
                        $engineerId
                    ) {

                        $query
                            ->where('store_id', $storeId)
                            ->whereJsonContains(
                                'assigned_engineers',
                                $engineerId
                            );
                    })
                    ->sum('amount');


                /*
                |--------------------------------------------------------------------------
                | Convert to float and round
                |--------------------------------------------------------------------------
                */

                $achievedAmount = round(
                    (float) $achievedAmount,
                    2
                );


                /*
                |--------------------------------------------------------------------------
                | Assigned Target
                |--------------------------------------------------------------------------
                */

                $assignedAmount = round(
                    (float) $target->assigned_amount,
                    2
                );


                /*
                |--------------------------------------------------------------------------
                | Remaining Target
                |--------------------------------------------------------------------------
                */

                $remainingAmount = max(
                    0,
                    round(
                        $assignedAmount - $achievedAmount,
                        2
                    )
                );


                /*
                |--------------------------------------------------------------------------
                | Save
                |--------------------------------------------------------------------------
                */

                $target->achieved_amount = $achievedAmount;

                $target->remaining_amount = $remainingAmount;

                $target->save();
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | DELETED
    |--------------------------------------------------------------------------
    */

    public function deleted(JobCard $jobCard): void
    {
        /*
        |--------------------------------------------------------------------------
        | If a Delivered Job Card is deleted,
        | recalculate the target.
        |--------------------------------------------------------------------------
        */

        if ($jobCard->status === 'Delivered') {
            $this->syncUserTargets($jobCard);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESTORED
    |--------------------------------------------------------------------------
    */

    public function restored(JobCard $jobCard): void
    {
        /*
        |--------------------------------------------------------------------------
        | If a deleted Delivered Job Card is restored,
        | recalculate target.
        |--------------------------------------------------------------------------
        */

        if ($jobCard->status === 'Delivered') {
            $this->syncUserTargets($jobCard);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FORCE DELETED
    |--------------------------------------------------------------------------
    */

    public function forceDeleted(JobCard $jobCard): void
    {
        /*
        |--------------------------------------------------------------------------
        | Nothing required here.
        |
        | Soft-delete normally triggers deleted().
        |--------------------------------------------------------------------------
        */
    }
}