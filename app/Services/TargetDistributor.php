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
    public static function createAndDistribute(Store $store, int $year, int $month, float $amount, bool $includePrevious = false, $createdBy = null): StoreTarget
    {
        return DB::transaction(function () use ($store, $year, $month, $amount, $includePrevious, $createdBy) {

            // Calculate previous remaining total
            $previousRemainingSum = $includePrevious
                ? DB::table('store_targets')
                    ->join('user_targets', 'store_targets.id', '=', 'user_targets.store_target_id')
                    ->where('store_targets.store_id', $store->id)
                    ->where(function ($q) use ($year, $month) {
                        $q->where('store_targets.year', '<', $year)
                            ->orWhere(fn($q2) => $q2->where('store_targets.year', $year)->where('store_targets.month', '<', $month));
                    })
                    ->where('user_targets.remaining_amount', '>', 0)
                    ->sum('user_targets.remaining_amount')
                : 0;

            $totalAmount = round($amount + $previousRemainingSum, 2);

            // ✅ Check if target already exists for this store, month, and year
            $storeTarget = StoreTarget::where('store_id', $store->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($storeTarget) {
                // If already distributed, skip to prevent duplicates
                if ($storeTarget->distributed) {
                    return $storeTarget;
                }

                // Update existing record
                $storeTarget->update([
                    'amount' => $totalAmount,
                    'include_previous' => $includePrevious,
                    'previous_remaining_sum' => $previousRemainingSum,
                    'created_by' => optional($createdBy)->id,
                ]);
            } else {
                // Otherwise, create a new record
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

            // Delete any existing user_targets before redistributing
            UserTarget::where('store_target_id', $storeTarget->id)->delete();

            // Fetch store members (excluding admins)
            $members = $store->users()
                ->whereHas('roles', function ($q) {
                    $q->where('name', '!=', 'admin');
                })
                ->get();

            $count = $members->count();

            if ($count === 0) {
                return $storeTarget;
            }

            // Equal distribution
            $base = floor(($totalAmount / $count) * 100) / 100;
            $remainder = round($totalAmount - ($base * $count), 2);

            foreach ($members as $index => $member) {
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

            $storeTarget->update(['distributed' => true]);

            return $storeTarget;
        });
    }
}
