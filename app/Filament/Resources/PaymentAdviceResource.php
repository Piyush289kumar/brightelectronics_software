<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentAdviceItemResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\PaymentAdviceResource\Pages;
use App\Filament\Resources\PaymentAdviceResource\RelationManagers;
use App\Models\Invoice;
use App\Models\PaymentAdvice;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentAdviceResource extends Resource
{
    protected static ?string $model = PaymentAdvice::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Payment Advice';
    protected static ?string $pluralModelLabel = 'Payment Advices';
    protected static ?string $label = 'Payment Advices';

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Payment Advice')
                ->schema([
                    Forms\Components\DatePicker::make('date')
                        ->label('Payment Date')
                        ->required()
                        ->default(now()),

                    Forms\Components\Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(Vendor::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('payment_advice_start_date')
                        ->label('Start Date')
                        ->default(now()->subDays(7))   // ⬅️ 7 days previous
                        ->required(),

                    Forms\Components\DatePicker::make('payment_advice_end_date')
                        ->label('End Date')
                        ->default(now())               // ⬅️ today
                        ->required(),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('load_po')
                            ->label('Load Purchase Orders')
                            ->color('success')
                            ->extraAttributes(['class' => 'w-full mt-6'])
                            ->action(function ($get, $set) {

                                $vendorId = $get('vendor_id');
                                $start = $get('payment_advice_start_date');
                                $end = $get('payment_advice_end_date');

                                if (!$vendorId || !$start || !$end) {
                                    return;
                                }

                                // 🔒 Already used PO IDs
                                $usedPoIds = \App\Models\PaymentAdviceItem::pluck('purchase_order_id')->toArray();

                                // ✅ Fetch ONLY unused Purchase Orders
                                $pos = Invoice::query()
                                    ->where('document_type', 'purchase_order')
                                    ->where('billable_id', $vendorId)
                                    ->whereBetween('document_date', [$start, $end])
                                    ->whereNotIn('id', $usedPoIds) // ⭐ KEY LINE
                                    ->get();

                                $rows = [];

                                foreach ($pos as $po) {
                                    $rows[] = [
                                        'purchase_order_id' => $po->id,
                                        'po_date' => $po->document_date,
                                        'po_number' => $po->number,
                                        'amount' => $po->total_amount,
                                        'payment_doc_no' => 0,
                                    ];
                                }

                                $set('items_data', $rows);
                            }),
                    ]),

                ])
                ->columns(5),

            Forms\Components\Section::make('Payment Advice Items')
                ->visible(fn(string $operation) => $operation === 'create')
                ->schema([
                    Forms\Components\Repeater::make('items_data')
                        ->visible(fn(string $operation) => $operation === 'create')
                        ->schema([
                            Forms\Components\DatePicker::make('po_date')->label('PON Date')->disabled(),
                            Forms\Components\TextInput::make('po_number')->label('PON')->disabled(),
                            Forms\Components\TextInput::make('invoice')->label('Invoice No.'),
                            Forms\Components\TextInput::make('amount')->label('Invoice Amount'),
                            Forms\Components\TextInput::make('payment_doc_no')->default(0)->label('Payment doc no.'),
                        ])
                        ->columns(5),
                ])
        ]);
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("vendor.name"),
                Tables\Columns\TextColumn::make("date")->date(),
                Tables\Columns\TextColumn::make("payment_doc_no"),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentAdvice::route('/'),
            'create' => Pages\CreatePaymentAdvice::route('/create'),
            'edit' => Pages\EditPaymentAdvice::route('/{record}/edit'),
        ];
    }
}
