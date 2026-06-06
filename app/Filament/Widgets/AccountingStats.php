<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Complain;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\StoreTarget;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AccountingStats extends BaseWidget
{
    protected ?string $heading = 'Service Overview';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user
            && (
                $user->hasRole(['Administrator', 'Developer', 'admin'])
            );
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        // -------------------------
        // Complaints Query
        // -------------------------
        $complainQuery = Complain::query();

        if (
            $user &&
            !$user->hasRole(['Administrator', 'Developer', 'admin']) &&
            $user->email !== 'vipprow@gmail.com'
        ) {
            $complainQuery->whereJsonContains('assigned_engineers', $user->id);
        }

        $complainCount = (clone $complainQuery)->where('first_action_code', 'NEW')
            ->count();

        $cncComplaints = (clone $complainQuery)
            ->where('first_action_code', 'CNC')
            ->count();

        $rsdComplaints = (clone $complainQuery)
            ->where('first_action_code', 'RSD')
            ->count();

        $jobCancelComplaints = (clone $complainQuery)
            ->where('first_action_code', 'Job Cancel')
            ->count();

        // -------------------------
        // Job Cards Query
        // -------------------------
        $jobCardQuery = JobCard::query();

        if (
            $user &&
            !$user->hasRole(['Administrator', 'Developer', 'admin']) &&
            $user->email !== 'vipprow@gmail.com'
        ) {
            $jobCardQuery->whereHas('complain', function ($q) use ($user) {
                $q->whereJsonContains('assigned_engineers', $user->id);
            });
        }

        $jobCardTotal = $jobCardQuery->count();

        $jobCardPending = (clone $jobCardQuery)
            ->where('status', 'pending')
            ->count();

        $jobCardReturn = (clone $jobCardQuery)
            ->where('status', 'Return')
            ->count();

        $jobCardCancelled = (clone $jobCardQuery)
            ->where('status', 'Cancelled')
            ->count();

        $jobCardCompleted = (clone $jobCardQuery)
            ->where('status', 'Complete')
            ->count();


        // ---------------- Purchases ----------------
        $purchaseRequisitionCount = PurchaseRequisition::where('status', 'pending')->count();

        $pendingPurchaseAmount = Invoice::where('document_type', 'purchase')
            ->whereIn('status', ['pending', 'draft'])
            ->sum('total_amount');

        // ---------------- Store Target (This Month) ----------------
        $year = now()->year;
        $month = now()->month;

        // If user belongs to a store
        $storeId = Auth::user()?->store_id;

        $storeTarget = StoreTarget::query()
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $targetAmount = $storeTarget?->amount ?? 0;
        $collectedAmount = $storeTarget?->collected_amount ?? 0;

        $percentage = $targetAmount > 0
            ? round(($collectedAmount / $targetAmount) * 100, 2)
            : 0;

        return [

            // ---------------- Complaints ----------------
            Stat::make('Total Complaints (New)', $complainCount)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->description('Assigned complaints'),

            Stat::make('CNC Complaints', $cncComplaints)
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('CNC complaints'),

            Stat::make('RSD Complaints', $rsdComplaints)
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('RSD complaints'),

            Stat::make('Cancel Complaints', $jobCancelComplaints)
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->description('Cancelled complaints'),

            // ---------------- Job Cards ----------------
            Stat::make('Total Job Cards', $jobCardTotal)
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->description('Assigned job cards'),

            Stat::make('Pending Job Cards', $jobCardPending)
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('Pending jobs'),

            Stat::make('Return Job Cards', $jobCardReturn)
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->description('Returned jobs'),

            Stat::make('Cancelled Job Cards', $jobCardCancelled)
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->description('Cancelled jobs'),

            Stat::make('Completed Job Cards', $jobCardCompleted)
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description('Successfully completed jobs'),


            // ---------------- Purchases ----------------
            Stat::make('Purchase Requisitions', $purchaseRequisitionCount)
                ->icon('heroicon-o-clipboard-document')
                ->color('warning')
                ->description('Total pending purchase requests'),

            // ---------------- Branch Target ----------------
            Stat::make(
                'Branch Target (This Month)',
                $storeTarget
                ? '₹' . number_format($collectedAmount, 2) . ' / ₹' . number_format($targetAmount, 2)
                : '-'
            )
                ->icon('heroicon-o-flag')
                ->color(
                    $storeTarget && $collectedAmount >= $targetAmount
                    ? 'success'
                    : 'warning'
                )
                ->description(
                    $storeTarget
                    ? "Achieved {$percentage}%"
                    : 'No target set for this month'
                ),

            // ---------------- Pending Purchase Amount ----------------
            Stat::make(
                'Pending Purchase Amount',
                '₹' . number_format($pendingPurchaseAmount, 2)
            )
                ->icon('heroicon-o-banknotes')
                ->color('danger')
                ->description('Outstanding payable amount'),
        ];
    }
}