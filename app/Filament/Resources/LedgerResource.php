<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerResource\Pages;
use App\Models\Ledger;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerResource extends Resource
{
    protected static ?string $model = Ledger::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Ledger Entries';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Grid::make(3)->schema([
                ToggleButtons::make('transaction_type')
                    ->label('Type')
                    ->options([
                        'debit' => 'Debit',
                        'credit' => 'Credit',
                    ])
                    ->required()
                    ->grouped()   // Groups buttons together like a toggle
                    ->inline()    // Shows them side-by-side
                    ->default('debit'),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->default(now())
                    ->label('Transaction Date'),

                Forms\Components\Select::make('account_id')
                    ->relationship('account', 'account_name') // ✅ Fix here
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Account'),
            ]),


            Grid::make(2)->schema([

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->prefix('₹')
                    ->label('Amount'),

                Forms\Components\TextInput::make('balance')
                    ->numeric()
                    ->disabled()
                    ->label('Running Balance'),
            ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('account.account_name')->label('Account')->searchable(),
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->colors([
                        'success' => 'debit',
                        'danger' => 'credit',
                    ])
                    ->label('Type'),

                Tables\Columns\TextColumn::make('amount')->money('inr')->sortable(),

                Tables\Columns\TextColumn::make('balance')->money('inr')->sortable(),

                Tables\Columns\TextColumn::make('journalEntry.reference')
                    ->label('Reference')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_id')
                    ->relationship('account', 'account_name')->searchable()

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgers::route('/'),
            // 'create' => Pages\CreateLedger::route('/create'),
            // 'edit' => Pages\EditLedger::route('/{record}/edit'),
        ];
    }
}
