<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingStats extends BaseWidget
{
    protected ?string $heading = 'Accounting Overview'; // ✅ Correct
    protected function getStats(): array
    {
        // Sum of all debit transactions from ledger
        $ledgerDebit = Ledger::where('transaction_type', 'debit')->sum('amount');

        // Sum of all accounts' opening balances that are debit
        $openingDebit = Account::where('balance_type', 'debit')->sum('opening_balance');

        // Total Debit = opening balances + ledger debits
        $totalDebit = $ledgerDebit + $openingDebit;

        // Total Credit
        $totalCredit = Ledger::where('transaction_type', 'credit')->sum('amount')
            + Account::where('balance_type', 'credit')->sum('opening_balance');

        $balance = $totalDebit - $totalCredit;

        return [
            Stat::make('Total Debit', '₹' . number_format($totalDebit, 2))
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->description('All debit transactions')
                ->chart([5, 10, 15, 20, 25, 30, 35]),

            Stat::make('Total Credit', '₹' . number_format($totalCredit, 2))
                ->icon('heroicon-o-arrow-up-circle')
                ->color('danger')
                ->description('All credit transactions')
                ->chart([5, 10, 15, 20, 25, 30, 35]),

            Stat::make('Net Balance', '₹' . number_format($balance, 2))
                ->icon('heroicon-o-banknotes')
                ->color($balance >= 0 ? 'success' : 'danger')
                ->description('Debits - Credits')
                ->chart([5, 10, 15, 20, 25, 30, 35]),
        ];
    }
}