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
        return $form
            ->schema([
                Forms\Components\TextInput::make('po_date')->label('Date')->disabled(),
                Forms\Components\TextInput::make('po_number')->label('PON Number')->disabled(),
                Forms\Components\TextInput::make('invoice_no')->label('Invoice No'),
                Forms\Components\TextInput::make('amount')->numeric()->disabled(),
                Forms\Components\TextInput::make('payment_doc_no')->label('Payment Doc No'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('po_number')
            ->columns([
                Tables\Columns\TextColumn::make('po_date'),
                Tables\Columns\TextColumn::make('purchaseOrder.number')->label('PO Number'),
                Tables\Columns\TextColumn::make('invoice.number')->label('Invoice Number'),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('payment_doc_no'),
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
