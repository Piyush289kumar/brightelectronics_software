<?php

namespace App\Filament\Widgets;

use App\Models\Ledger;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class LedgerWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Ledger Transactions';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user?->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
            'Manager',
            'Store Manager',
            'Team Leader',
            'Team Lead',
        ]) ?? false;
    }

    public function table(Tables\Table $table): Tables\Table
    {
        $user = Auth::user();

        $query = Ledger::query();

        $isAdmin = $user?->hasAnyRole([
            'Administrator',
            'Developer',
            'admin',
        ]);

        if (! $isAdmin) {
            $storeIds = $user?->multi_store_id ?? [];

            $storeIds = is_array($storeIds)
                ? array_map('intval', $storeIds)
                : [];

            if (empty($storeIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('store_id', $storeIds);
            }
        }

        return $table
            ->query($query->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->toggleable(),

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

                Tables\Columns\TextColumn::make('narration')
                    ->label('Narration')
                    ->limit(30),
            ])
            ->paginated(false);
    }
}
