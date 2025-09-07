<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments'; // matches Invoice::payments() relation

    protected static ?string $recordTitleAttribute = 'amount';

    // ❌ Remove static

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('amount')
                    ->label('Amount Received')
                    ->numeric()
                    ->required()
                    ->default(fn() => number_format($this->ownerRecord?->balance ?? 0, 2, '.', ''))
                    ->maxValue(fn() => number_format($this->ownerRecord?->balance ?? 0, 2, '.', ''))
                    ->step(0.01), // allows only 2 decimal places

                Forms\Components\DatePicker::make('payment_date')
                    ->label('Payment Date')
                    ->default(now())
                    ->required(),

                Forms\Components\Select::make('method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'bank' => 'Bank Transfer',
                        'upi' => 'UPI',
                        'cheque' => 'Cheque',
                        'card' => 'Card',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('reference_no')
                    ->label('Reference No.'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes'),
            ]);
    }

    // ❌ Remove static
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')->money('INR'),
                Tables\Columns\TextColumn::make('payment_date')->date(),
                Tables\Columns\TextColumn::make('method')->label('Payment Method'),
                Tables\Columns\TextColumn::make('reference_no'),
                Tables\Columns\TextColumn::make('status'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Receive Payment'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
