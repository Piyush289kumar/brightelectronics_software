<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseRequisitionResource\Pages;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\Store;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class PurchaseRequisitionResource extends Resource
{
    protected static ?string $model = PurchaseRequisition::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Purchase';
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    protected static ?string $navigationBadgeTooltip = 'Total Purchase Requisitions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    Select::make('store_id')
                        ->label('Store (Requesting)')
                        ->options(Store::pluck('name', 'id'))
                        ->default(fn() => Auth::user()->store_id ?? null)
                        ->required()
                        ->disabled(fn() => Auth::user()?->isStoreManager() ?? false)
                        ->dehydrated(true),
                    TextInput::make('reference')->label('Reference'),
                    Select::make('priority')
                        ->label('Priority')
                        ->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                        ])
                        ->default('medium'),
                    Textarea::make('notes')->label('Notes')->rows(1)->columnSpan('full'),
                ]),
                Repeater::make('items')
                    ->relationship('items')
                    ->label('Requested Items')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->options(Product::pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->required()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('purchase_price', $product->purchase_price);
                                        }
                                    }
                                }),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $set('total_price', $state * ($get('purchase_price') ?? 0));
                                }),
                            TextInput::make('purchase_price')
                                ->label('Purchase Unit Price')
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->reactive()
                                ->default(0)
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $set('total_price', $state * ($get('quantity') ?? 0));
                                }),
                            Grid::make(2)->schema([
                                TextInput::make('total_price')
                                    ->label('Total Price')
                                    ->numeric()
                                    ->disabled()
                                    ->default(0)
                                    ->dehydrated(true) // 👈 important: store in DB
                                    ->required(),
                                Textarea::make('note')->label('Note')->rows(1),
                            ])
                        ])
                    ])
                    ->columnSpan('full')
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Show only pending by default
                $query->where('status', 'pending');
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('store.name')->label('Store')->sortable(),
                Tables\Columns\TextColumn::make('requester.name')->label('Requested By'),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle') // ✅ Add icon
                    ->color('success') // ✅ green color
                    ->modalHeading('Approve / Fulfill Requisition')
                    ->visible(fn($record) => Auth::user()?->isAdmin() ?? false)
                    ->form([
                        Select::make('method')
                            ->label('Fulfillment Method')
                            ->options([
                                'purchase' => 'Purchase from Vendor',
                                'transfer' => 'Transfer from Another Store',
                            ])
                            ->reactive()
                            ->required(),
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->options(Vendor::pluck('name', 'id'))
                            ->visible(fn($get) => $get('method') === 'purchase')
                            ->reactive() // 👈 make it reactive
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                // loop through repeater rows
                                $items = $get('items') ?? [];
                                foreach ($items as $index => $item) {
                                    // only set vendor if not already chosen in row
                                    if (empty($item['vendor_id'])) {
                                        $items[$index]['vendor_id'] = $state;
                                    }
                                }
                                $set('items', $items);
                            }),
                        Select::make('destination_store_id')
                            ->label('Destination Store')
                            ->options(function (callable $get, $record) {
                                // fall back to requisition's store_id if not inside the modal
                                $storeId = $get('store_id') ?? $record?->store_id;
                                return Store::query()
                                    ->where('id', '!=', $storeId)
                                    ->pluck('name', 'id');
                            })
                            ->visible(fn($get) => $get('method') === 'transfer')
                            ->required(fn($get) => $get('method') === 'transfer'),
                        Repeater::make('items')
                            ->label('Approve quantities (per item)')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextInput::make('id')->hidden()->dehydrated(),
                                    TextInput::make('product_name')->label('Product')->disabled(),
                                    TextInput::make('quantity')->label('Requested Qty')->disabled(),
                                    TextInput::make('purchase_price')->label('Requested Price')->disabled(),
                                    Select::make('vendor_id')
                                        ->label('Vendor')
                                        ->options(Vendor::pluck('name', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->dehydrated(true)
                                        ->reactive(),
                                    TextInput::make('approved_quantity')
                                        ->label('Approved Qty')
                                        ->numeric()
                                        ->required(),
                                    TextInput::make('approved_price')
                                        ->label('Approved Price')
                                        ->numeric()
                                        ->required(),
                                    TextInput::make('approved_total')
                                        ->label('Approved Total')
                                        ->disabled()
                                        ->dehydrated(true),
                                ]),
                            ])
                            ->default(function ($record, $get) {
                                $globalVendor = $get('vendor_id');
                                return $record?->items?->map(fn($i) => [
                                    'id' => $i->id,
                                    'product_name' => $i->product->name,
                                    'quantity' => $i->quantity,
                                    'purchase_price' => $i->purchase_price,
                                    'approved_quantity' => $i->approved_quantity ?? $i->quantity,
                                    'approved_price' => $i->approved_price ?? $i->purchase_price,
                                    'approved_total' => ($i->approved_quantity ?? $i->quantity) * ($i->approved_price ?? $i->purchase_price),
                                    'vendor_id' => $i->vendor_id ?? $globalVendor, // 👈 correctly set default vendor_id
                                ])->toArray() ?? [];
                            })
                            ->columns('full'),
                    ])
                    ->action(function (PurchaseRequisition $record, array $data) {
                        $globalVendor = $data['vendor_id'] ?? null;

                        // 1. Save approved quantities + vendor
                        foreach ($record->items as $index => $item) {
                            $approvedQuantity = $data['items'][$index]['approved_quantity'] ?? null;
                            $approvedPrice = $data['items'][$index]['approved_price'] ?? $item->purchase_price ?? 0;

                            // 👇 If no vendor chosen per line, fallback to global vendor
                            $vendorId = $data['items'][$index]['vendor_id'] ?? $globalVendor;

                            if ($approvedQuantity !== null) {
                                $item->approved_quantity = (int) $approvedQuantity;
                                $item->approved_price = $approvedPrice;
                                $item->total_price = $approvedQuantity * $approvedPrice;
                                $item->vendor_id = $vendorId;
                                $item->save();
                            }
                        }

                        // 2. Handle fulfillment
                        if ($data['method'] === 'purchase') {
                            $allItems = $record->items;

                            // Ensure every line has vendor
                            $missingVendorCount = $allItems->whereNull('vendor_id')->count();
                            if ($missingVendorCount > 0) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'method' => "{$missingVendorCount} item(s) don't have a vendor selected.",
                                ]);
                            }

                            // ✅ FIX: group by vendor_id properly
                            $byVendor = $allItems->groupBy(fn($i) => (int) $i->vendor_id);

                            foreach ($byVendor as $vendorId => $vendorItems) {
                                $invoice = Invoice::create([
                                    'document_type' => 'purchase_order',
                                    'billable_id' => $vendorId,
                                    'billable_type' => Vendor::class,
                                    'document_date' => now(),
                                    'status' => 'pending',
                                    'notes' => $record->notes,
                                    'created_by' => Auth::id(),
                                ]);

                                $invoiceItems = $vendorItems->map(function ($i) {
                                    $qty = $i->approved_quantity ?? $i->quantity ?? 0;
                                    $unitPrice = $i->approved_price ?? $i->purchase_price ?? 0;

                                    return [
                                        'product_id' => $i->product_id,
                                        'description' => $i->product?->name,
                                        'quantity' => $qty,
                                        'unit_price' => $unitPrice,
                                        'total_amount' => $qty * $unitPrice,
                                    ];
                                })->values()->all();

                                $invoice->items()->createMany($invoiceItems);
                                $invoice->update(['total_amount' => collect($invoiceItems)->sum('total_amount')]);
                            }
                        } elseif ($data['method'] === 'transfer') {
                            // TRANSFER: single transfer order
                            $fromStoreId = $data['destination_store_id'] ?? null;
                            $toStoreId = $record->store_id;

                            $invoice = Invoice::create([
                                'document_type' => 'transfer_order',
                                'billable_id' => $fromStoreId,
                                'billable_type' => Store::class,
                                'destination_store_id' => $toStoreId,
                                'document_date' => now(),
                                'status' => 'pending',
                                'notes' => $record->notes,
                                'created_by' => Auth::id(),
                            ]);

                            $items = $record->items->map(function ($item) {
                                return [
                                    'product_id' => $item->product_id,
                                    'description' => $item->product?->name,
                                    'quantity' => $item->approved_quantity ?? $item->quantity,
                                    'unit_price' => 0,
                                    'total_amount' => 0,
                                ];
                            })->values()->all();

                            $invoice->items()->createMany($items);
                            $invoice->update(['total_amount' => 0]);
                        }

                        // 3. Mark requisition approved
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Reject'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkFulfill')
                        ->label('Bulk Fulfill')
                        ->icon('heroicon-o-truck')
                        ->modalWidth('7xl') // 👈 makes modal wider (xl, 2xl, 3xl, 4xl)
                        ->requiresConfirmation()
                        ->form(function (Collection $records) {
                            // 👇 Collect & merge all requisition items by product
                            $allItems = $records->flatMap(fn($req) => $req->items);

                            $merged = $allItems->groupBy('product_id')->map(function ($items) {
                                $qty = $items->sum(fn($i) => $i->approved_quantity ?? $i->quantity ?? 0);
                                $unitPrice = $items->first()->approved_price ?? $items->first()->purchase_price ?? 0;

                                return [
                                    'product_id' => $items->first()->product_id,
                                    'product_name' => $items->first()->product?->name,
                                    'quantity' => $qty,
                                    'unit_price' => $unitPrice,
                                    'total_amount' => $qty * $unitPrice,
                                    'vendor_id' => $items->first()->vendor_id, // prefill if set
                                ];
                            })->values()->all();

                            return [
                                Select::make('method')
                                    ->label('Fulfillment Method')
                                    ->options([
                                        'purchase' => 'Purchase from Vendor (multi-vendor)',
                                        'transfer' => 'Transfer from Another Store',
                                    ])
                                    ->required()
                                    ->reactive(),

                                Select::make('source_store_id')
                                    ->label('Source Store (for transfer)')
                                    ->options(Store::pluck('name', 'id'))
                                    ->visible(fn($get) => $get('method') === 'transfer')
                                    ->required(fn($get) => $get('method') === 'transfer'),

                                Repeater::make('products')
                                    ->label('Products to Purchase')
                                    ->visible(fn($get) => $get('method') === 'purchase')
                                    ->default($merged) // 👈 load merged products
                                    ->schema([
                                        Grid::make(2)->schema([
                                             TextInput::make('product_name')->disabled()->label('Product')->dehydrated(true),
                                             TextInput::make('quantity')->disabled()->label('Total Qty')->dehydrated(true),
                                             TextInput::make('unit_price')->disabled()->label('Unit Price')->dehydrated(true),
                                        TextInput::make('total_amount')->disabled()->label('Total')->dehydrated(true),
                                        ]),
                                        // Grid::make(4)->schema([]),
                                       
                                        Select::make('vendor_id')
                                            ->label('Vendor')
                                            ->options(Vendor::pluck('name', 'id'))
                                            ->searchable()
                                            ->required(),
                                    ])
                                    ->columns('full'),
                            ];
                        })
                        ->action(function (Collection $records, array $data) {
                            $firstReq = $records->first();

                            if ($data['method'] === 'purchase') {
                                // ✅ User-provided vendor selection per product
                                $grouped = collect($data['products'])->groupBy('vendor_id');

                                foreach ($grouped as $vendorId => $vendorItems) {
                                    $invoice = Invoice::create([
                                        'document_type' => 'purchase_order',
                                        'billable_id' => $vendorId,
                                        'billable_type' => Vendor::class,
                                        'document_date' => now(),
                                        'status' => 'pending',
                                        'notes' => 'Bulk requisition purchase',
                                        'created_by' => Auth::id(),
                                    ]);

                                    $invoice->items()->createMany($vendorItems->map(fn($i) => [
                                        'product_id' => $i['product_id'],
                                        'description' => $i['product_name'],
                                        'quantity' => $i['quantity'],
                                        'unit_price' => $i['unit_price'],
                                        'total_amount' => $i['total_amount'],
                                    ])->all());

                                    $invoice->update(['total_amount' => $vendorItems->sum('total_amount')]);
                                }
                            } else {
                                // TRANSFER: single transfer order
                                $byProduct = $records->flatMap(fn($req) => $req->items)
                                    ->groupBy('product_id')
                                    ->map(fn($items) => $items->sum(fn($i) => $i->approved_quantity ?? $i->quantity ?? 0));

                                $invoice = Invoice::create([
                                    'document_type' => 'transfer_order',
                                    'billable_id' => $data['source_store_id'],
                                    'billable_type' => Store::class,
                                    'destination_store_id' => $firstReq?->store_id,
                                    'document_date' => now(),
                                    'status' => 'pending',
                                    'notes' => 'Bulk requisition transfer',
                                    'created_by' => Auth::id(),
                                ]);

                                $items = $byProduct->map(function ($qty, $productId) {
                                    $product = Product::find($productId);
                                    return [
                                        'product_id' => $productId,
                                        'description' => $product?->name,
                                        'quantity' => $qty,
                                        'unit_price' => 0,
                                        'total_amount' => 0,
                                    ];
                                })->values()->all();

                                $invoice->items()->createMany($items);
                                $invoice->update(['total_amount' => 0]);
                            }

                            // Mark requisitions approved
                            foreach ($records as $requisition) {
                                $requisition->update([
                                    'status' => 'approved',
                                    'approved_by' => Auth::id(),
                                    'approved_at' => now(),
                                ]);
                            }
                        }),


                        ExportBulkAction::make(),
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
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseRequisitions::route('/'),
            // 'create' => Pages\CreatePurchaseRequisition::route('/create'),
            // 'edit' => Pages\EditPurchaseRequisition::route('/{record}/edit'),
        ];
    }
}
