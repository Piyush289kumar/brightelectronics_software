<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\JobCard;
use App\Models\Ledger;
use App\Models\UserTarget;
use Illuminate\Support\Facades\DB;
use App\Models\User;

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
            'lead_incentive_amount',
            'expense',
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
            $jobCard,
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
                    ->whereHas('storeTarget', function ($query) use ($year, $month) {
                        $query->where('year', $year)
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

                // $achievedAmount = JobCard::query()
                //     ->where('status', 'Delivered')
                //     ->whereHas('complain', function ($query) use (
                //         $storeId,
                //         $engineerId
                //     ) {

                //         $query
                //             ->where('store_id', $storeId)
                //             ->whereJsonContains(
                //                 'assigned_engineers',
                //                 $engineerId
                //             );
                //     })
                //     ->sum('amount');

                // ================================================
                // CALCULATE ACTUAL ACHIEVED TARGET
                // Delivered Job Card Amount - Lead Incentive - Expense
                // ================================================

                // ================================================
                // CALCULATE DISTRIBUTED ACHIEVED TARGET
                // ================================================

                $deliveredJobCards = JobCard::query()
                    ->with('complain:id,assigned_engineers,store_id')
                    ->where('status', 'Delivered')
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->whereHas('complain', function ($query) use ($storeId) {
                        $query->where('store_id', $storeId);
                    })
                    ->get();

                // Normalize assigned engineer IDs
                $normalizeAssignedIds = function ($assigned) {
                    return collect($assigned ?? [])
                        ->map(function ($engineer) {
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
                };

                // Collect all assigned user IDs
                $allAssignedIds = $deliveredJobCards
                    ->flatMap(function ($card) use ($normalizeAssignedIds) {
                        return $normalizeAssignedIds(
                            $card->complain?->assigned_engineers
                        );
                    })
                    ->unique()
                    ->values();

                // Load users and their roles
                $assignedUsers = User::with('roles')
                    ->whereIn('id', $allAssignedIds)
                    ->get()
                    ->keyBy('id');

                // Distribute target contribution
                $achievedByUser = [];

                foreach ($deliveredJobCards as $card) {

                    $assignedIds = $normalizeAssignedIds(
                        $card->complain?->assigned_engineers
                    );

                    if ($assignedIds->isEmpty()) {
                        continue;
                    }

                    // Actual target contribution
                    $contribution = (float) $card->amount
                        - (float) $card->lead_incentive_amount
                        - (float) $card->expense;

                    // Identify Machine Men
                    $machineMenIds = $assignedIds
                        ->filter(function ($id) use ($assignedUsers) {
                            return $assignedUsers->get($id)
                                ?->hasRole('Machine Men') ?? false;
                        })
                        ->values();


                    // Debug only the JobCard that triggered this observer
                    // if ((int) $card->id === (int) $jobCard->id) {
                    //     dd([
                    //         'current_observer_job_card_id' => $jobCard->id,
                    //         'current_observer_complain_id' => $jobCard->complain_id,

                    //         'loop_job_card_id' => $card->id,
                    //         'loop_complain_id' => $card->complain_id,

                    //         'complain_assigned_engineers' => $card->complain?->assigned_engineers,

                    //         'assigned_ids' => $assignedIds->all(),

                    //         'assigned_users' => $assignedIds->map(function ($id) use ($assignedUsers) {
                    //             $user = $assignedUsers->get($id);

                    //             return [
                    //                 'id' => $id,
                    //                 'name' => $user?->name,
                    //                 'roles' => $user?->getRoleNames()->all(),
                    //                 'is_machine_man' => $user?->hasRole('Machine Men') ?? false,
                    //             ];
                    //         })->values()->all(),

                    //         'machine_men_ids' => $machineMenIds->all(),
                    //     ]);
                    // }


                    if ($machineMenIds->isNotEmpty()) {

                        // Machine Men gets 100%; engineers get 0%
                        $eligibleIds = $machineMenIds;
                    } else {

                        // No Machine Man: divide equally among engineers
                        $eligibleIds = $assignedIds;
                    }

                    $share = $contribution / $eligibleIds->count();

                    foreach ($eligibleIds as $userId) {
                        $achievedByUser[$userId] =
                            ($achievedByUser[$userId] ?? 0) + $share;
                    }
                }

                // Get achieved amount for the current target owner
                $achievedAmount = round(
                    (float) ($achievedByUser[$engineerId] ?? 0),
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
