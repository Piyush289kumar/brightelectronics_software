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
use App\Models\UserTarget;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AccountingStats extends BaseWidget
{
    protected ?string $heading = 'Service Overview';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
            'Team Leader',
            'Team Lead',
            'Engineer',
            'Machine Men',
        ]) ?? false;
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

        $complainCount = (clone $complainQuery)
            ->whereIn('first_action_code', ['NEW', 'Visit'])
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
        $isSuperAdmin = $user->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
            'Team Leader',
            'Team Lead',
        ]);

        $isStoreManager = $user->hasAnyRole([
            'Manager',
            'Store Manager',
        ]);

        if ($isSuperAdmin) {

            // All stores total
            $targetAmount = StoreTarget::where('year', $year)
                ->where('month', $month)
                ->sum('amount');

            $collectedAmount = StoreTarget::where('year', $year)
                ->where('month', $month)
                ->sum('collected_amount');

            $targetTitle = 'Company Target (This Month)';

        } elseif ($isStoreManager) {

            // Current store only
            $storeTarget = StoreTarget::where('store_id', $user->store_id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            $targetAmount = $storeTarget?->amount ?? 0;
            $collectedAmount = $storeTarget?->collected_amount ?? 0;

            $targetTitle = 'Branch Target (This Month)';

        } else {

            // Engineer / Machine Men
            $userTarget = UserTarget::where('user_id', $user->id)
                ->whereHas('storeTarget', function ($q) use ($year, $month) {
                    $q->where('year', $year)
                        ->where('month', $month);
                })
                ->first();

            $targetAmount = $userTarget?->assigned_amount ?? 0;
            $collectedAmount = $userTarget?->achieved_amount ?? 0;

            $targetTitle = 'My Target (This Month)';
        }

        $percentage = $targetAmount > 0
            ? round(($collectedAmount / $targetAmount) * 100, 2)
            : 0;

        return [

            // ---------------- Complaints ----------------
            Stat::make('Total Complaints', $complainCount)
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

            Stat::make('Completed & Tested Job Cards', $jobCardCompleted)
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description('Ready for delivery'),


            // ---------------- Purchases ----------------
            Stat::make('Purchase Requisitions', $purchaseRequisitionCount)
                ->icon('heroicon-o-clipboard-document')
                ->color('warning')
                ->description('Total pending purchase requests'),

            // ---------------- Branch Target ----------------
            Stat::make(
                $targetTitle,
                '₹' . number_format($collectedAmount, 2) .
                ' / ₹' . number_format($targetAmount, 2)
            )
                ->icon('heroicon-o-flag')
                ->color($percentage >= 100 ? 'success' : 'warning')
                ->description("Achieved {$percentage}%"),

            // ---------------- Pending Purchase Amount ----------------
            Stat::make(
                'Pending Purchase Amount',
                '₹' . number_format($pendingPurchaseAmount, 2)
            )
                ->icon('heroicon-o-banknotes')
                ->color('danger')
                ->description('Outstanding payable amount'),
        ];

        // Engineer & Machine Men should only see Complaint + Job Card stats
        if ($user->hasAnyRole(['Engineer', 'Machine Men'])) {
            return $stats;
        }
    }
}