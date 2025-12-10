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
        return $form
            ->schema([

                Forms\Components\Section::make("Basic Details")
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->label('Payment Advice Date')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make("vendor_id")
                            ->label("Vendor")
                            ->options(Vendor::pluck("name", "id"))
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn($set) => $set('meta', [])),
                    ])->columns(2),

                Forms\Components\Section::make("Filter Purchase Orders")
                    ->schema([

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make("start_date")
                                ->label("Start Date")
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn($set) => $set('po_data', [])),

                            Forms\Components\DatePicker::make("end_date")
                                ->label("End Date")
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn($set) => $set('po_data', [])),


                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('load_purchase_orders')
                                    ->label('Load Purchase Orders')
                                    ->button()
                                    ->color('success')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->extraAttributes(['class' => 'w-full mt-6'])->action(function (callable $get, callable $set) {

                                        $vendorId = $get('vendor_id');
                                        $startDate = $get('start_date');
                                        $endDate = $get('end_date');

                                        if (!$vendorId || !$startDate || !$endDate) {
                                            return;
                                        }

                                        $pos = Invoice::where("document_type", "purchase_order")
                                            ->where("billable_id", $vendorId)
                                            ->whereBetween("document_date", [$startDate, $endDate])
                                            ->get();

                                        $rows = [];
                                        $i = 1;

                                        foreach ($pos as $po) {
                                            $invoice = Invoice::where("billable_id", $po->billable_id)
                                                ->where("document_type", "invoice")
                                                ->orderBy("id")
                                                ->first();

                                            $rows[] = [
                                                "sr_no" => $i++,
                                                "po_id" => $po->id,
                                                "invoice_id" => $invoice?->id,
                                                "po_date" => $po->document_date,
                                                "po_number" => $po->number,
                                                "invoice_no" => $invoice?->number ?? null,
                                                "amount" => $invoice?->total_amount ?? 0,
                                                "payment_doc_no" => "PAD-" . str_pad($po->id, 4, "0", STR_PAD_LEFT),
                                            ];
                                        }

                                        $set("items_data", $rows);
                                    }),
                            ])->alignment('right'),

                        ]),



                    ])->columns(1),

                Forms\Components\Section::make("Purchase Advice Items")
                    ->schema([
                        Forms\Components\Repeater::make('items_data')
                            ->schema([
                                Forms\Components\TextInput::make("sr_no")->label("Sr No")->disabled(),
                                Forms\Components\DatePicker::make("po_date")->label("PO Date")->disabled(),
                                Forms\Components\TextInput::make("po_number")->label("PO Number")->disabled(),
                                Forms\Components\TextInput::make("invoice_no")->label("Invoice No")->disabled(),
                                Forms\Components\TextInput::make("amount")->numeric()->label("Amount")->disabled(),
                                Forms\Components\TextInput::make("payment_doc_no")->label("Payment Doc No")->disabled(),
                            ])
                            ->columns(6)
                            ->default([])
                    ]),

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
