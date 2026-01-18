<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Complain;
use App\Models\JobCard;
use App\Models\Ledger;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingStats extends BaseWidget
{
    protected ?string $heading = 'Service Overview';

    protected function getStats(): array
    {
        $complainCount = Complain::count();

        $jobCardTotal = JobCard::count();

        $jobCardPending = JobCard::where('status', 'pending')->count();

        $jobCardCompleted = JobCard::where('status', 'completed')->count();

        return [
            Stat::make('Total Complaints', $complainCount)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->description('All registered complaints'),

            Stat::make('Total Job Cards', $jobCardTotal)
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->description('All job cards'),

            Stat::make('Pending Job Cards', $jobCardPending)
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('Jobs in progress'),

            Stat::make('Completed Job Cards', $jobCardCompleted)
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description('Successfully completed jobs'),
        ];
    }
}