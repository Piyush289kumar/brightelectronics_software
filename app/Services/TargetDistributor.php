<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreTarget;
use App\Models\UserTarget;
use Illuminate\Support\Facades\DB;

class TargetDistributor
{
    /**
     * Create or update & distribute a store target for month/year.
     */
    public static function createAndDistribute(
        Store $store,
        int $year,
        int $month,
        float $amount,
        bool $includePrevious = false,
        $createdBy = null
    ): StoreTarget {

        return DB::transaction(function () use ($store, $year, $month, $amount, $includePrevious, $createdBy) {

            // =============================
            // ✅ PREVIOUS REMAINING CALCULATION
            // =============================
            $previousRemainingSum = $includePrevious
                ? DB::table('store_targets')
                    ->join('user_targets', 'store_targets.id', '=', 'user_targets.store_target_id')
                    ->where('store_targets.store_id', $store->id)
                    ->where(function ($q) use ($year, $month) {
                        $q->where('store_targets.year', '<', $year)
                            ->orWhere(function ($q2) use ($year, $month) {
                                $q2->where('store_targets.year', $year)
                                    ->where('store_targets.month', '<', $month);
                            });
                    })
                    ->where('user_targets.remaining_amount', '>', 0)
                    ->sum('user_targets.remaining_amount')
                : 0;

            $totalAmount = round($amount + $previousRemainingSum, 2);

            // =============================
            // ✅ FIND OR CREATE STORE TARGET
            // =============================
            $storeTarget = StoreTarget::where('store_id', $store->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($storeTarget) {

                // 🚫 Prevent duplicate distribution
                if ($storeTarget->distributed) {
                    return $storeTarget;
                }

                // ✅ Update existing
                $storeTarget->update([
                    'amount' => $totalAmount,
                    'include_previous' => $includePrevious,
                    'previous_remaining_sum' => $previousRemainingSum,
                    'created_by' => optional($createdBy)->id,
                ]);

            } else {

                // ✅ Create new
                $storeTarget = StoreTarget::create([
                    'store_id' => $store->id,
                    'year' => $year,
                    'month' => $month,
                    'amount' => $totalAmount,
                    'include_previous' => $includePrevious,
                    'previous_remaining_sum' => $previousRemainingSum,
                    'created_by' => optional($createdBy)->id,
                    'distributed' => false,
                ]);
            }

            // =============================
            // ✅ DELETE OLD DISTRIBUTION
            // =============================
            UserTarget::where('store_target_id', $storeTarget->id)->delete();

            // =============================
            // ✅ FETCH MEMBERS (Engineer + Machine Men)
            // =============================
            $members = $store->users()
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['Engineer', 'Machine Men']);
                })
                ->whereDoesntHave('roles', function ($q) {
                    $q->whereIn('name', ['Manager', 'Team Lead', 'Administrator', 'admin']);
                })
                ->get();

            $count = $members->count();

            if ($count === 0) {
                return $storeTarget;
            }

            // =============================
            // ✅ EQUAL DISTRIBUTION
            // =============================
            $base = floor(($totalAmount / $count) * 100) / 100;
            $remainder = round($totalAmount - ($base * $count), 2);

            foreach ($members as $index => $member) {

                // Give remainder to first user
                $extra = $index === 0 ? $remainder : 0;

                $assigned = round($base + $extra, 2);

                UserTarget::create([
                    'store_target_id' => $storeTarget->id,
                    'user_id' => $member->id,
                    'assigned_amount' => $assigned,
                    'remaining_amount' => $assigned,
                    'achieved_amount' => 0.00,
                ]);
            }

            // =============================
            // ✅ MARK AS DISTRIBUTED
            // =============================
            $storeTarget->update(['distributed' => true]);

            return $storeTarget;
        });
    }
}