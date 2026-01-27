<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Complain;
use App\Models\JobCard;
use App\Models\Ledger;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AccountingStats extends BaseWidget
{
    protected ?string $heading = 'Service Overview';

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

        $complainCount = $complainQuery->where('first_action_code', 'NEW')
            ->count();

        // 🔥 NEW COUNTS
        $cncComplaints = (clone $complainQuery)
            ->where('first_action_code', 'CNC')
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

        $jobCardCompleted = (clone $jobCardQuery)
            ->where('status', 'completed')
            ->count();

        return [

            // ---------------- Complaints ----------------
            Stat::make('Total Complaints', $complainCount)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->description('Assigned complaints'),

            Stat::make('PKD Complaints', $cncComplaints)
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->description('CNC complaints'),

            Stat::make('Job Cancel Complaints', $jobCancelComplaints)
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

            Stat::make('Completed Job Cards', $jobCardCompleted)
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description('Successfully completed jobs'),
        ];
    }
}