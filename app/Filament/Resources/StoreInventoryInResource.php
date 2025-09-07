<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreInventoryInResource\Pages;
use App\Filament\Resources\StoreInventoryInResource\RelationManagers;
use App\Models\Store;
use App\Models\StoreInventoryIn;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;


class StoreInventoryInResource extends Resource
{
    protected static ?string $model = StoreInventoryIn::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Stores';
    protected static ?string $navigationLabel = 'Stock In';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                // ----------------------------
                // Important Info (Top Section)
                // ----------------------------
                Section::make('Important Information')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('store_id')
                                ->label('Store')
                                ->options(Store::pluck('name', 'id'))
                                ->default(fn() => Auth::user()?->store_id ?? Store::first()?->id)
                                ->required()
                                ->disabled(fn() => Auth::user()?->isStoreManager() ?? false)
                                ->dehydrated(fn($state, $context) => true), // ensure it’s sent even if disabled

                            Select::make('received_by')
                                ->label('Received By')
                                ->relationship('receiver', 'name')
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state === 'other') {
                                        $set('received_by', null); // important: set null instead of 'other'
                                        $set('received_by_text', '');
                                    }
                                })
                                ->options(fn() => User::pluck('name', 'id')->toArray() + ['other' => 'Other'])
                                ->required(fn($get) => $get('received_by_text') === null),

                            TextInput::make('received_by_text')
                                ->label('Received By (Type)')
                                ->visible(fn($get) => $get('received_by') === null)
                                ->required(fn($get) => $get('received_by') === null),

                            Select::make('transaction_type')
                                ->label('Transaction Type')
                                ->options([
                                    'stock_in' => 'Stock In',
                                    'transfer' => 'Transfer from Another Store',
                                    'return' => 'Return',
                                    'adjustment' => 'Stock Adjustment',
                                    'damaged' => 'Damaged',
                                ])
                                ->default('stock_in')
                                ->required(),

                            Select::make('vendor_id')
                                ->label('Vendor')
                                ->relationship('vendor', 'name')
                                ->searchable(),

                            TextInput::make('invoice_no')->label('Invoice No'),
                            DatePicker::make('invoice_date')->label('Invoice Date')->default(now()),
                        ])
                    ]),

                // ----------------------------
                // Delivery & Vehicle Info
                // ----------------------------
                Section::make('Delivery & Transport Info')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('vehicle_no')->label('Vehicle No'),
                            TextInput::make('driver_name')->label('Driver Name'),
                            TextInput::make('driver_contact')->label('Driver Contact'),
                            TextInput::make('delivery_person')->label('Delivery Person'),
                            TextInput::make('delivery_info')->label('Delivery Info')->columnSpanFull(),
                        ])
                    ]),

                // ----------------------------
                // Payment Info
                // ----------------------------
                Section::make('Payment & Amounts')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('grand_total')->label('Grand Total')->numeric()->default(0),
                            TextInput::make('payment_method')->label('Payment Method'),
                            DatePicker::make('payment_date')->label('Payment Date'),
                            Select::make('payment_status')
                                ->label('Payment Status')
                                ->options([
                                    'pending' => 'Pending',
                                    'paid' => 'Paid',
                                    'partial' => 'Partial',
                                ])
                                ->default('pending'),
                            TextInput::make('discount_amount')->label('Discount')->numeric()->default(0),
                            TextInput::make('tax_amount')->label('Tax')->numeric()->default(0),
                        ])
                    ]),

                // ----------------------------
                // Documents / Attachments
                // ----------------------------
                Section::make('Attachments')
                    ->schema([
                        FileUpload::make('documents')
                            ->label('Upload Documents (PDF / Images)')
                            ->multiple()
                            ->directory('store-inventory-in')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->enableOpen()
                            ->enableDownload()
                            ->maxFiles(10)
                            ->helperText('Upload invoices, delivery challans, or other documents.'),
                    ]),

                // ----------------------------
                // Notes
                // ----------------------------
                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')->label('Additional Notes'),
                    ]),

                // ----------------------------
                // Inventory Items
                // ----------------------------
                Section::make('Stock Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name') // works because SiteInventoryIssueItem has product()
                                    ->required()
                                    ->searchable(),


                                TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                Textarea::make('note')
                                    ->rows(1),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('receiver_display')
                    ->label('Received By')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        // If a receiver exists, show their name; otherwise show typed text
                        return $record->receiver?->name ?? $record->received_by_text;
                    }),

                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice_no')
                    ->label('Invoice No')
                    ->sortable()
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('inr')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Payment Status')
                    ->sortable()
                    ->getStateUsing(fn($record) => match ($record->payment_status) {
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        default => $record->payment_status,
                    })
                    ->colors([
                        'danger' => fn($state) => $state === 'Pending',
                        'success' => fn($state) => $state === 'Paid',
                        'warning' => fn($state) => $state === 'Partial',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('transaction_type')
                    ->label('Transaction Type')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name'),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('to')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $from) => $q->whereDate('created_at', '>=', $from))
                            ->when($data['to'], fn($q, $to) => $q->whereDate('created_at', '<=', $to));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()
            ])
            ->defaultSort('created_at', 'desc');
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreInventoryIns::route('/'),
            'create' => Pages\CreateStoreInventoryIn::route('/create'),
            'edit' => Pages\EditStoreInventoryIn::route('/{record}/edit'),
        ];
    }

    /**
     * Restrict floors listing to manager's store.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && $user->isStoreManager()) {
            $query->where('store_id', $user->store_id);
        }

        return $query;
    }
}
