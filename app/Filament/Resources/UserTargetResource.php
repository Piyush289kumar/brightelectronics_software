<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserTargetResource\Pages;
use App\Filament\Resources\UserTargetResource\RelationManagers;
use App\Models\UserTarget;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Auth;

class UserTargetResource extends Resource
{
    protected static ?string $model = UserTarget::class;


    protected static ?string $navigationGroup = 'Targets';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Member Targets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('assigned_amount')
                    ->label('Assigned Target (₹)')
                    ->numeric()
                    ->suffix('INR'),

                TextInput::make('achieved_amount')
                    ->label('Collection Amount (₹)')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('INR')
                    ->live(debounce: 300) // Live updates remaining field
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $assigned = $get('assigned_amount') ?? 0;
                        $remaining = max($assigned - $state, 0);
                        $set('remaining_amount', round($remaining, 2));
                    }),

                TextInput::make('remaining_amount')
                    ->label('Remaining Target (₹)')
                    ->numeric()
                    ->suffix('INR')
                    ->disabled()
                    ->dehydrated(true), // store value in DB
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('user.name')->label('Member'),

                Tables\Columns\TextColumn::make('storeTarget.store.name')->label('Store'),

                Tables\Columns\TextColumn::make('storeTarget.month')
                    ->label('Month')
                    ->formatStateUsing(fn($state, $record) => "{$record->storeTarget->month}/{$record->storeTarget->year}"),

                Tables\Columns\TextColumn::make('assigned_amount')
                    ->label('Assigned Target (₹)')
                    ->money('INR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('achieved_amount')
                    ->label('Total Collection (₹)')
                    ->money('INR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining (₹)')
                    ->money('INR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime(),
            ])
            ->filters([
                Tables\Filters\Filter::make('current_month')
                    ->label('Current Month')
                    ->default()
                    ->query(function ($query) {
                        return $query->whereHas('storeTarget', function ($q) {
                            $q->where('month', now()->month)
                                ->where('year', now()->year);
                        });
                    }),

                Tables\Filters\SelectFilter::make('month')
                    ->label('Month')
                    ->options([
                        1 => 'Jan',
                        2 => 'Feb',
                        3 => 'Mar',
                        4 => 'Apr',
                        5 => 'May',
                        6 => 'Jun',
                        7 => 'Jul',
                        8 => 'Aug',
                        9 => 'Sep',
                        10 => 'Oct',
                        11 => 'Nov',
                        12 => 'Dec',
                    ])
                    ->query(
                        fn($query, $data) =>
                        $query->when(
                            $data['value'],
                            fn($q, $month) =>
                            $q->whereHas('storeTarget', fn($sq) => $sq->where('month', $month))
                        )
                    ),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Year')
                    ->options(
                        collect(range(now()->year - 5, now()->year + 1))
                            ->mapWithKeys(fn($y) => [$y => $y])
                    )
                    ->query(
                        fn($query, $data) =>
                        $query->when(
                            $data['value'],
                            fn($q, $year) =>
                            $q->whereHas('storeTarget', fn($sq) => $sq->where('year', $year))
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->hasRole([
                        'Administrator',
                        'Developer',
                        'admin',
                    ]) || auth()->user()->email === 'vipprow@gmail.com'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // Safety
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // 🔹 Admin / Developer / Super access → see ALL
        if (
            $user->hasRole(['Administrator', 'Developer', 'admin']) ||
            $user->email === 'vipprow@gmail.com'
        ) {
            return $query;
        }

        // 🔹 Store Manager restriction (optional – keep if needed)
        if ($user->hasRole('Store Manager')) {
            return $query->whereHas('storeTarget', function ($q) use ($user) {
                $q->where('store_id', $user->store_id);
            });
        }

        // 🔹 Normal user → see ONLY own targets
        return $query->where('user_id', $user->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserTargets::route('/'),
            // 'create' => Pages\CreateUserTarget::route('/create'),
            // 'edit' => Pages\EditUserTarget::route('/{record}/edit'),
        ];
    }
}
