<?php

namespace App\Filament\Resources\PaymentAdviceItemResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'po_number';

    public function form(Form $form): Form
    {
        return $form->schema([


            // ✅ Auto-filled PO Date
            Forms\Components\DatePicker::make('po_date')
                ->label('PO Date')
                ->readOnly()
                ->required(),

            // ✅ Purchase Order Dropdown
            Forms\Components\Select::make('purchase_order_id')
                ->label('Purchase Order (PON)')
                ->options(
                    \App\Models\Invoice::where('document_type', 'purchase_order')
                        ->orderBy('id', 'desc')
                        ->pluck('number', 'id')
                )
                ->searchable()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (!$state) {
                        return;
                    }
                    $po = \App\Models\Invoice::find($state);
                    if (!$po) {
                        return;
                    }
                    // ✅ Auto-fill fields
                    $set('po_date', $po->document_date);
                    $set('amount', $po->total_amount);
                }),
            // ✅ Auto-filled Amount
            Forms\Components\TextInput::make('amount')
                ->label('Amount')
                ->numeric()
                ->dehydrated()
                ->disabled()
                ->required(),
            // Payment document number
            Forms\Components\TextInput::make('payment_doc_no')
                ->label('Payment Doc No')
                ->required(),
        ])
            ->columns(2);
    }


    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('po_number')
            ->columns([
                Tables\Columns\TextColumn::make('po_date')->label('PON Date'),
                Tables\Columns\TextColumn::make('purchaseOrder.number')->label('PON'),
                Tables\Columns\TextColumn::make('invoice.number')->label('Invoice No.'),
                Tables\Columns\TextColumn::make('amount')->label('Invoice Amount'),
                Tables\Columns\TextColumn::make('payment_doc_no')->label('Payment Doc No.'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
