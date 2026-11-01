<?php

namespace App\Filament\Widgets;


use App\Models\Complain;
use App\Models\Invoice;
use App\Models\JobCard;
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
            'Manager',
            'Store Manager',
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

        if (! $user) {
            return [];
        }

        // =====================================================
        // ROLES
        // =====================================================

        $isAdmin = $user->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
        ]);

        $isBranchManagement = $user->hasAnyRole([
            'Manager',
            'Store Manager',
            'Team Leader',
            'Team Lead',
        ]);

        $isEngineer = $user->hasAnyRole([
            'Engineer',
            'Machine Men',
        ]);

        if (! $isAdmin && ! $isBranchManagement && ! $isEngineer) {
            return [];
        }

        // =====================================================
        // STORE ACCESS - ONLY multi_store_id
        // =====================================================

        $storeIds = $user->multi_store_id ?? [];

        $storeIds = is_array($storeIds)
            ? array_map('intval', $storeIds)
            : [];

        $userId = (int) $user->id;

        if (! $isAdmin && empty($storeIds)) {
            return [];
        }

        // =====================================================
        // COMPLAINTS
        // =====================================================

        $complainQuery = Complain::query();

        if (! $isAdmin) {
            $complainQuery->whereIn('store_id', $storeIds);

            // Engineer / Machine Men: only assigned complaints
            if ($isEngineer) {
                $complainQuery->where(function ($q) use ($userId) {
                    $q->whereJsonContains('assigned_engineers', $userId)
                        ->orWhereJsonContains(
                            'assigned_engineers',
                            (string) $userId
                        );
                });
            }
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

        // =====================================================
        // JOB CARDS
        // =====================================================

        $jobCardQuery = JobCard::query()
            ->whereHas('complain');

        if (! $isAdmin) {
            $jobCardQuery->whereHas(
                'complain',
                function ($q) use ($storeIds, $isEngineer, $userId) {
                    $q->whereIn('store_id', $storeIds);

                    // Engineer / Machine Men: only assigned complaints
                    if ($isEngineer) {
                        $q->where(function ($assignmentQuery) use ($userId) {
                            $assignmentQuery
                                ->whereJsonContains(
                                    'assigned_engineers',
                                    $userId
                                )
                                ->orWhereJsonContains(
                                    'assigned_engineers',
                                    (string) $userId
                                );
                        });
                    }
                }
            );
        }

        $jobCardTotal = (clone $jobCardQuery)->count();

        $jobCardPending = (clone $jobCardQuery)
            ->where('status', 'Pending')
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

        // =====================================================
        // PURCHASES
        // =====================================================

        // These queries retain their existing global scope.
        // Engineers and Machine Men do not see purchase cards.

        $purchaseRequisitionCount = PurchaseRequisition::where(
            'status',
            'pending'
        )->count();

        $pendingPurchaseAmount = Invoice::where(
            'document_type',
            'purchase'
        )
            ->whereIn('status', ['pending', 'draft'])
            ->sum('total_amount');

        // =====================================================
        // TARGET
        // =====================================================

        $year = now()->year;
        $month = now()->month;

        $targetAmount = 0;
        $collectedAmount = 0;
        $targetTitle = 'Target (This Month)';

        if ($isAdmin) {

            // Admin: all branches combined

            $targetAmount = StoreTarget::where('year', $year)
                ->where('month', $month)
                ->sum('amount');

            $collectedAmount = StoreTarget::where('year', $year)
                ->where('month', $month)
                ->sum('collected_amount');

            $targetTitle = 'Company Target (This Month)';

        } elseif ($isBranchManagement) {

            // Manager / Team Leader: all assigned stores combined

            $storeTargets = StoreTarget::whereIn('store_id', $storeIds)
                ->where('year', $year)
                ->where('month', $month)
                ->get();

            $targetAmount = (float) $storeTargets->sum('amount');

            $collectedAmount = (float) $storeTargets->sum(
                'collected_amount'
            );

            $targetTitle = 'Assigned Stores Target (This Month)';

        } elseif ($isEngineer) {

            // Engineer: personal target only

            $userTarget = UserTarget::where('user_id', $userId)
                ->whereHas('storeTarget', function ($q) use ($year, $month) {
                    $q->where('year', $year)
                        ->where('month', $month);
                })
                ->first();

            $targetAmount = (float) ($userTarget?->assigned_amount ?? 0);

            $collectedAmount = (float) ($userTarget?->achieved_amount ?? 0);

            $targetTitle = 'My Target (This Month)';
        }

        $percentage = $targetAmount > 0
            ? round(($collectedAmount / $targetAmount) * 100, 2)
            : 0;

        // =====================================================
        // ENGINEER / MACHINE MEN DASHBOARD
        // =====================================================

        if ($isEngineer) {
            return [

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

                Stat::make(
                    'My Target (This Month)',
                    '₹' . number_format($collectedAmount, 2)
                    . ' / ₹' . number_format($targetAmount, 2)
                )
                    ->icon('heroicon-o-flag')
                    ->color($percentage >= 100 ? 'success' : 'warning')
                    ->description("Achieved {$percentage}%"),
            ];
        }

        // =====================================================
        // ADMIN + MANAGER + TEAM LEADER DASHBOARD
        // =====================================================

        return [

            // ---------------- COMPLAINTS ----------------

            Stat::make('Total Complaints', $complainCount)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->description(
                    $isAdmin ? 'All branches' : 'All assigned stores'
                ),

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

            // ---------------- JOB CARDS ----------------

            Stat::make('Total Job Cards', $jobCardTotal)
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->description(
                    $isAdmin ? 'All branches' : 'All assigned stores'
                ),

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

            // ---------------- PURCHASES ----------------

            Stat::make('Purchase Requisitions', $purchaseRequisitionCount)
                ->icon('heroicon-o-clipboard-document')
                ->color('warning')
                ->description('Total pending purchase requests'),

            // ---------------- TARGET ----------------

            Stat::make(
                $targetTitle,
                '₹' . number_format($collectedAmount, 2)
                . ' / ₹' . number_format($targetAmount, 2)
            )
                ->icon('heroicon-o-flag')
                ->color($percentage >= 100 ? 'success' : 'warning')
                ->description("Achieved {$percentage}%"),

            // ---------------- PENDING PURCHASE ----------------

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
