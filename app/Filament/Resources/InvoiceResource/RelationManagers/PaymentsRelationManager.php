<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Payment;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments'; // Invoice::payments()
    protected static ?string $recordTitleAttribute = 'amount';

    public function form(Form $form): Form
    {
        $isPurchase = $this->ownerRecord->document_type === 'purchase';

        return $form
            ->schema([
                TextInput::make('amount')
                    ->label($isPurchase ? 'Amount to Pay' : 'Amount to Receive')
                    ->numeric()
                    ->required()
                    ->default(fn() => number_format($this->ownerRecord?->balance ?? 0, 2, '.', ''))
                    ->maxValue(fn() => number_format($this->ownerRecord?->balance ?? 0, 2, '.', ''))
                    ->step(0.01),

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
                Tables\Actions\CreateAction::make()
                    ->label(fn() => $this->ownerRecord->document_type === 'purchase' ? 'Make Payment' : 'Receive Payment')
                    ->action(function ($record, array $data) {
                        $isPurchase = $record->document_type === 'purchase';

                        Payment::create([
                            'invoice_id' => $record->id,
                            'payable_id' => $record->id,
                            'payable_type' => $record::class,
                            'type' => $isPurchase ? 'outgoing' : 'incoming',
                            'amount' => $data['amount'],
                            'payment_date' => $data['payment_date'],
                            'method' => $data['method'],
                            'reference_no' => $data['reference_no'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'status' => 'completed',
                            'received_by' => auth()->id(),
                            'created_by' => auth()->id(),
                        ]);
                    }),
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
