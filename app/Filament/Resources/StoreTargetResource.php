<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreTargetResource\Pages;
use App\Models\Store;
use App\Models\StoreTarget;
use App\Services\TargetDistributor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Auth;

class StoreTargetResource extends Resource
{
    protected static ?string $model = StoreTarget::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $label = 'Branch Targets';
    protected static ?string $navigationGroup = 'Targets';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('store_id')
                ->label('Branch')
                ->options(fn() => Store::pluck('name', 'id'))
                ->searchable()
                ->required(),

            TextInput::make('year')
                ->label('Year')
                ->numeric()
                ->default(now()->year)
                ->minValue(2020)
                ->maxValue(now()->year + 1)
                ->required(),

            TextInput::make('month')
                ->label('Month (1–12)')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->default(now()->month)
                ->required(),

            TextInput::make('amount')
                ->label('Target Amount (₹)')
                ->numeric()
                ->minValue(0.01)
                ->required(),

            Toggle::make('include_previous')
                ->label('Include Previous Remaining')
                ->helperText('If enabled, remaining targets from previous months will be added to this target.')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('store.name')->label('Store')->sortable()->searchable(),
            TextColumn::make('year')->sortable(),
            TextColumn::make('month')->sortable(),
            TextColumn::make('amount')->label('Target (₹)')->money('INR'),
            TextColumn::make('previous_remaining_sum')->label('Prev. Remaining (₹)')->money('INR'),
            TextColumn::make('userTargets_sum_assigned_amount')
                ->label('Distributed Total (₹)')
                ->state(fn($record) => $record->userTargets()->sum('assigned_amount'))
                ->money('INR')
                ->sortable(),
            Tables\Columns\TextColumn::make('collected_amount')
                ->label('Total Collection (₹)')
                ->money('INR')
                ->sortable(),
            Tables\Columns\TextColumn::make('remaining_target')
                ->label('Remaining (₹)')
                ->state(fn($record) => max($record->amount - $record->collected_amount, 0))
                ->money('INR')
                ->sortable(),

            IconColumn::make('distributed')->label('Distributed')->boolean(),
            TextColumn::make('created_at')->label('Created')->dateTime(),
        ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('distribute')
                    ->label('Distribute Target')
                    ->icon('heroicon-o-chart-bar')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function (StoreTarget $record) {
                        if (!$record->distributed) {
                            TargetDistributor::createAndDistribute(
                                $record->store,
                                $record->year,
                                $record->month,
                                (float) $record->amount,
                                (bool) $record->include_previous,
                                Auth::user()
                            );
                        }
                    })
                    ->visible(fn(StoreTarget $record) => !$record->distributed),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreTargets::route('/'),
            // 'create' => Pages\CreateStoreTarget::route('/create'),
            // 'edit' => Pages\EditStoreTarget::route('/{record}/edit'),
        ];
    }
}
