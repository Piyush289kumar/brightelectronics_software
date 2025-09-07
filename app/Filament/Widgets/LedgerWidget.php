<?php

namespace App\Filament\Widgets;

use App\Models\Ledger;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;

class LedgerWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full'; // full width in dashboard

    protected static ?string $heading = 'Recent Ledger Transactions';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                Ledger::query()->latest()->limit(10) // fetch latest 10 entries
            )
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.account_name')
                    ->label('Account')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->colors([
                        'success' => 'debit',
                        'danger' => 'credit',
                        'warning' => 'adjustment',
                        'info' => 'opening_balance',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('INR', true)
                    ->label('Amount')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance')
                    ->money('INR', true)
                    ->label('Balance')
                    ->sortable(),

                Tables\Columns\TextColumn::make('narration')
                    ->label('Narration')
                    ->limit(30),
            ])
            ->paginated(false); // no pagination, since we limit to 10
    }
}
