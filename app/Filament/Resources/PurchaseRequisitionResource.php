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
use Filament\Forms\Components\FileUpload;
use App\Models\Category;
use App\Models\StoreInventory;
use App\Models\PurchaseRequisitionItemFulfillment;
use Filament\Forms\Get;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequisitionResource extends Resource
{
    protected static ?string $model = PurchaseRequisition::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Purchase';
    protected static ?int $navigationSort = 2;

    protected static function permission(string $action): string
    {
        return "{$action}_purchase::requisition";
    }

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
                    // Select::make('store_id')
                    //     ->label('Branch (Requesting)')
                    //     ->options(
                    //         Store::orderBy('name')
                    //             ->get()
                    //             ->mapWithKeys(fn($store) => [
                    //                 $store->id => "{$store->name} ({$store->code})",
                    //             ])
                    //     )
                    //     ->searchable()
                    //     ->preload()
                    //     ->default(fn() => Auth::user()->store_id)
                    //     ->required()
                    //     ->disabled(fn() => Auth::user()?->isStoreManager())
                    //     ->dehydrated(true),

                    Forms\Components\Select::make('store_id')
                        ->relationship(
                            name: 'store',
                            titleAttribute: 'code',
                            modifyQueryUsing: function (Builder $query) {
                                $user = Auth::user();
                                if (! $user) {
                                    return $query->whereRaw('1 = 0');
                                }
                                if ($user->hasAnyRole(['Administrator', 'Developer', 'admin'])) {
                                    return $query;
                                }
                                $storeIds = $user->multi_store_id ?? [];

                                return $query->whereIn('id', is_array($storeIds) ? $storeIds : []);
                            }
                        )
                        ->preload()
                        ->required()
                        ->label('Branch')
                        ->default(fn() => collect(Auth::user()?->multi_store_id ?? [])->first())
                        ->live()
                        ->afterStateUpdated(fn(callable $set) => $set('assigned_engineers', []))
                        ->dehydrated(true)
                        ->options(
                            fn() => Store::query()
                                ->get()
                                ->mapWithKeys(fn($store) => [
                                    $store->id => "{$store->name} ({$store->code})",
                                ])
                                ->toArray()
                        ),

                    Select::make('job_card_id')
                        ->label('Link Job Card')
                        ->relationship(
                            name: 'jobCard',
                            titleAttribute: 'job_id',
                            modifyQueryUsing: fn(Builder $query) => $query->orderByDesc('id')
                        )
                        ->searchable()
                        ->preload()
                        ->nullable(),

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
                    ->addable(false)
                    ->schema([
                        Grid::make(columns: 3)->schema([
                            Select::make('product_id')
                                ->label('Spare Parts')
                                ->relationship(
                                    name: 'product',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn($query) => $query->where('is_active', true)
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn(Product $record) => "{$record->name} ({$record->barcode})"
                                )
                                ->searchable(['name', 'barcode']) // Search by Name + Part Number
                                ->preload()
                                ->required()
                                ->reactive()

                                ->createOptionForm([
                                    Grid::make(2)->schema([

                                        TextInput::make('name')
                                            ->label('Spare Part Name')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('barcode')
                                            ->label('Part No.')
                                            ->maxLength(100),

                                        // ✅ Category
                                        Select::make('category_id')
                                            ->label('Category')
                                            ->options(Category::whereNull('parent_id')->pluck('name', 'id'))
                                            ->reactive()
                                            ->searchable(),

                                        // ✅ Sub Category (only if category selected)
                                        Select::make('sub_category_id')
                                            ->label('Sub Category')
                                            ->options(function (callable $get) {
                                                $categoryId = $get('category_id');

                                                if (!$categoryId) {
                                                    return [];
                                                }

                                                return Category::where('parent_id', $categoryId)
                                                    ->pluck('name', 'id');
                                            })
                                            ->visible(fn(callable $get) => $get('category_id'))
                                            ->searchable(),

                                        // ✅ Image Upload
                                        FileUpload::make('image_path')
                                            ->label('Product Image')
                                            ->image()
                                            ->directory('products')
                                            ->imagePreviewHeight('100')
                                            ->columnSpanFull(),

                                    ])
                                ])

                                // ✅ Save logic
                                ->createOptionUsing(function (array $data): int {

                                    $categoryId = $data['sub_category_id'] ?? $data['category_id'] ?? null;

                                    $product = Product::create([
                                        'name' => $data['name'],
                                        'barcode' => $data['barcode'] ?? null,
                                        'category_id' => $categoryId, // ✅ save subcategory if exists
                                        'image_path' => $data['image_path'] ?? null,
                                        'is_active' => false, // ✅ always inactive
                                        'purchase_price' => 0,
                                        'selling_price' => 0,
                                    ]);

                                    return $product->id;
                                })
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('purchase_price', $product->purchase_price);
                                        }
                                    }
                                })
                                ->getOptionLabelUsing(function ($value): ?string {
                                    $product = Product::find($value);

                                    return $product
                                        ? "{$product->name} ({$product->barcode})"
                                        : null;
                                })
                                ->columnSpanFull(),

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
                                ->label('Rete')
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->reactive()
                                ->default(0)
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $set('total_price', $state * ($get('quantity') ?? 0));
                                }),

                            TextInput::make('total_price')
                                ->label('Total Price')
                                ->numeric()
                                ->disabled()
                                ->default(0)
                                ->dehydrated(true) // 👈 important: store in DB
                                ->required(),
                            Textarea::make('note')->label('Note')->rows(1)->columnSpanFull(),

                            Forms\Components\FileUpload::make('uom')
                                ->label('Product Pictures')
                                ->disk('public')
                                ->directory('request_products')
                                ->image()
                                ->imageEditor()
                                ->multiple()
                                ->maxFiles(3)
                                ->reorderable()
                                ->appendFiles()
                                ->openable()
                                ->downloadable()
                                ->previewable()
                                ->panelLayout('grid')
                                ->imagePreviewHeight('180')
                                ->nullable()
                                ->columnSpanFull(),

                            Grid::make(1)->schema([])
                        ]),

                        Forms\Components\Placeholder::make('linked_stock')
                            ->label('Available Linked Products & Branch Stock')
                            ->content(function (Get $get, $record) {
                                $productId = $get('product_id');

                                if (! $productId || ! $record?->store_id) {
                                    return 'Product or branch not found.';
                                }

                                $product = Product::find($productId);

                                if (! $product) {
                                    return 'Product not found.';
                                }

                                $products = $product->allLinkedProducts()
                                    ->push($product)
                                    ->unique('id');

                                if ($products->isEmpty()) {
                                    return 'No linked products available.';
                                }

                                $stock = StoreInventory::query()
                                    ->where('store_id', $record->store_id)
                                    ->whereIn('product_id', $products->pluck('id'))
                                    ->pluck('quantity', 'product_id');

                                return new \Illuminate\Support\HtmlString(
                                    '<div class="space-y-2">' .
                                        $products->map(function ($item) use ($stock) {
                                            $qty = (int) ($stock[$item->id] ?? 0);

                                            return '<div class="flex justify-between border-b py-2">' .
                                                '<span>' . e($item->name) . ' (' . e($item->barcode) . ')</span>' .
                                                '<strong>' . $qty . '</strong>' .
                                                '</div>';
                                        })->implode('') .
                                        '</div>'
                                );
                            })
                            ->visible(fn(Get $get) => $get('../../method') === 'consume')
                            ->columnSpanFull(),

                        Repeater::make('allocations')
                            ->label('Select Products to Consume from Branch Stock')
                            ->visible(fn(Get $get) => $get('../../method') === 'consume')
                            ->addActionLabel('Add Product')
                            ->default([])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Available Linked Products')
                                    ->required()
                                    ->searchable()
                                    ->options(function (Get $get, $record) {
                                        $requestedProductId = $get('../../product_id');
                                        $storeId = $record?->store_id;

                                        if (! $requestedProductId || ! $storeId) {
                                            return [];
                                        }

                                        $product = Product::find($requestedProductId);

                                        if (! $product) {
                                            return [];
                                        }

                                        $linkedIds = $product->allLinkedProducts()
                                            ->pluck('id')
                                            ->push($product->id)
                                            ->unique()
                                            ->values();

                                        $products = Product::query()
                                            ->whereIn('id', $linkedIds)
                                            ->where('is_active', true)
                                            ->get();

                                        $stock = StoreInventory::query()
                                            ->where('store_id', $storeId)
                                            ->whereIn('product_id', $linkedIds)
                                            ->pluck('quantity', 'product_id');

                                        return $products->mapWithKeys(function ($item) use ($stock) {
                                            $available = (int) ($stock[$item->id] ?? 0);

                                            return [
                                                $item->id => "{$item->name} ({$item->barcode}) — Available: {$available}",
                                            ];
                                        })->toArray();
                                    })
                                    ->live()
                                    ->distinct(),

                                TextInput::make('quantity')
                                    ->label('Consume Quantity')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpan('full')
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery();

        // ✅ Restrict visibility for non-admin users
        if (!$user->hasRole(['Administrator', 'Developer', 'admin']) && $user->email !== 'vipprow@gmail.com') {
            $query->where('requested_by', $user->id);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('store.name')->label('Branch')->sortable(),
                Tables\Columns\TextColumn::make('jobCard.job_id')->label('Job Card')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('items.fulfillments.product.name')->label('Consumed Products')->listWithLineBreaks()->toggleable(),
                Tables\Columns\TextColumn::make('requester.name')->label('Requested By'),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle') // ✅ Add icon
                        ->color('success') // ✅ green color
                        // 👇 DISABLE if not pending
                        ->disabled(fn($record) => $record->status !== 'pending')

                        // (optional but recommended)
                        ->tooltip(
                            fn($record) =>
                            $record->status !== 'pending'
                                ? 'This requisition is already approved'
                                : null
                        )
                        ->modalHeading('Approve / Fulfill Requisition')
                        ->visible(fn($record) => Auth::user()?->isAdmin() ?? false)
                        ->form([
                            Select::make('method')
                                ->label('Fulfillment Method')
                                ->options([
                                    'purchase' => 'Purchase from Vendor',
                                    'transfer' => 'Transfer from Another Branch',
                                    'consume' => 'Consume Available Stock (Original / Linked Products)',
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
                                ->label('Source Branch')
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
                                // ✅ REMOVE ADD BUTTON
                                ->addable(false)

                                // ✅ REMOVE DELETE BUTTON
                                ->deletable(false)

                                // ✅ REMOVE REORDER
                                ->reorderable(false)

                                ->schema([
                                    Grid::make(4)->schema([
                                        TextInput::make('id')->hidden()->dehydrated(),

                                        TextInput::make('product_name')
                                            ->label('Spare Parts')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->afterStateHydrated(function ($component, $state, $record) {

                                                if (!$record?->product_id) {
                                                    return;
                                                }

                                                $product = Product::find($record->product_id);

                                                if (!$product) {
                                                    return;
                                                }

                                                $component->state(
                                                    $product->name . ' (' . $product->barcode . ')'
                                                );
                                            }),

                                        TextInput::make('quantity')
                                            ->label('Requested Qty')
                                            ->disabled(),

                                        Select::make('vendor_id')
                                            ->label('Vendor')
                                            ->options(Vendor::pluck('name', 'id'))
                                            ->searchable()
                                            ->reactive()
                                            ->visible(
                                                fn($get) =>
                                                $get('../../method') === 'purchase'
                                            )
                                            ->required(
                                                fn($get) =>
                                                $get('../../method') === 'purchase'
                                            ),

                                        TextInput::make('approved_quantity')
                                            ->label('Approved Qty')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->lazy()
                                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                $price = (float) ($get('approved_price') ?? 0);
                                                $set('approved_total', (float) $state * $price);
                                            }),

                                        TextInput::make('approved_price')
                                            ->label('Approved Price')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->lazy()
                                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                $qty = (float) ($get('approved_quantity') ?? 0);
                                                $set('approved_total', $qty * (float) $state);
                                            }),

                                        TextInput::make('approved_total')
                                            ->label('Approved Total')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->numeric()
                                            ->default(0),
                                    ]),


                                    Forms\Components\Placeholder::make('linked_products')
                                        ->label('Available Linked Products')
                                        ->content(function (callable $get) {
                                            $productId = $get('product_id');

                                            if (! $productId) {
                                                return 'Select a product first.';
                                            }

                                            $product = Product::find($productId);

                                            if (! $product) {
                                                return 'Product not found.';
                                            }

                                            $linkedProducts = $product->allLinkedProducts();

                                            if ($linkedProducts->isEmpty()) {
                                                return 'No linked products available.';
                                            }

                                            return $linkedProducts
                                                ->map(fn($item) => "{$item->name} ({$item->barcode})")
                                                ->implode(', ');
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columns('full')
                                ->default(function ($record, $get) {

                                    $globalVendor = $get('vendor_id');

                                    return $record?->items?->map(fn($i) => [

                                        'id' => $i->id,

                                        // ✅ IMPORTANT
                                        'product_id' => $i->product_id,

                                        // ✅ SHOW NAME + BARCODE
                                        'product_name' =>
                                        $i->product?->name .
                                            ' (' .
                                            $i->product?->barcode .
                                            ')',

                                        'quantity' => $i->quantity,

                                        'purchase_price' => $i->purchase_price,

                                        'approved_quantity' =>
                                        $i->approved_quantity ?? $i->quantity,

                                        'approved_price' =>
                                        $i->approved_price ?? $i->purchase_price,

                                        'approved_total' => ($i->approved_quantity ?? $i->quantity)
                                            *
                                            ($i->approved_price ?? $i->purchase_price),

                                        'vendor_id' =>
                                        $i->vendor_id ?? $globalVendor,

                                    ])->toArray() ?? [];
                                })
                                ->columns('full'),
                        ])
                        ->action(function (PurchaseRequisition $record, array $data) {
                            $globalVendor = $data['vendor_id'] ?? null;

                            // Check inactive products
                            $inactiveProducts = $record->items
                                ->filter(fn($item) => !$item->product || !$item->product->is_active);

                            if ($inactiveProducts->isNotEmpty()) {

                                $productNames = $inactiveProducts
                                    ->map(fn($item) => $item->product?->name)
                                    ->implode(', ');

                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'method' => "Approval failed. Please activate these product(s) first: {$productNames}",
                                ]);
                            }


                            // CONSUME EXISTING BRANCH STOCK
                            if ($data['method'] === 'consume') {

                                DB::transaction(function () use ($record, $data) {

                                    // Lock requisition to prevent duplicate approval
                                    $requisition = PurchaseRequisition::query()
                                        ->lockForUpdate()
                                        ->findOrFail($record->id);

                                    if ($requisition->status !== 'pending') {
                                        throw ValidationException::withMessages([
                                            'method' => 'This requisition has already been processed.',
                                        ]);
                                    }

                                    $items = $requisition->items()->with('product')->get();

                                    // Match submitted repeater data using the hidden item ID
                                    $submittedItems = collect($data['items'] ?? [])
                                        ->keyBy(fn($row) => $row['id'] ?? null);

                                    foreach ($items as $item) {

                                        $itemData = $submittedItems->get($item->id);

                                        if (! $itemData) {
                                            throw ValidationException::withMessages([
                                                'method' => "Missing approval details for {$item->product?->name}.",
                                            ]);
                                        }

                                        $approvedQuantity = (int) (
                                            $itemData['approved_quantity'] ?? $item->quantity
                                        );

                                        if ($approvedQuantity <= 0) {
                                            throw ValidationException::withMessages([
                                                'method' => "Approved quantity must be greater than zero for {$item->product?->name}.",
                                            ]);
                                        }

                                        // Original product plus linked products from both directions
                                        $allowedProductIds = $item->product
                                            ->allLinkedProducts()
                                            ->pluck('id')
                                            ->push($item->product_id)
                                            ->unique()
                                            ->map(fn($id) => (int) $id)
                                            ->values()
                                            ->all();

                                        $allocations = collect($itemData['allocations'] ?? []);

                                        if ($allocations->isEmpty()) {
                                            throw ValidationException::withMessages([
                                                'method' => "Select at least one product for {$item->product?->name}.",
                                            ]);
                                        }

                                        // Aggregate repeated product selections
                                        $allocations = $allocations
                                            ->groupBy(fn($allocation) => (int) ($allocation['product_id'] ?? 0))
                                            ->map(fn($rows, $productId) => [
                                                'product_id' => (int) $productId,
                                                'quantity' => $rows->sum(
                                                    fn($row) => (int) ($row['quantity'] ?? 0)
                                                ),
                                            ])
                                            ->values();

                                        $totalAllocated = $allocations->sum('quantity');

                                        if ($totalAllocated !== $approvedQuantity) {
                                            throw ValidationException::withMessages([
                                                'method' => "For {$item->product?->name}, allocated quantity ({$totalAllocated}) must equal approved quantity ({$approvedQuantity}).",
                                            ]);
                                        }

                                        foreach ($allocations as $allocation) {

                                            $productId = $allocation['product_id'];
                                            $quantity = (int) $allocation['quantity'];

                                            if (! in_array($productId, $allowedProductIds, true)) {
                                                throw ValidationException::withMessages([
                                                    'method' => "Selected product is not linked to {$item->product?->name}.",
                                                ]);
                                            }

                                            if ($quantity <= 0) {
                                                throw ValidationException::withMessages([
                                                    'method' => 'Allocation quantity must be greater than zero.',
                                                ]);
                                            }

                                            // Lock the actual branch stock row
                                            $inventory = StoreInventory::query()
                                                ->where('store_id', $requisition->store_id)
                                                ->where('product_id', $productId)
                                                ->lockForUpdate()
                                                ->first();

                                            if (! $inventory || $inventory->quantity < $quantity) {
                                                $productName = Product::find($productId)?->name ?? 'Selected product';
                                                $available = $inventory?->quantity ?? 0;

                                                throw ValidationException::withMessages([
                                                    'method' => "{$productName}: requested {$quantity}, but only {$available} available in this branch.",
                                                ]);
                                            }

                                            // Deduct actual selected product stock
                                            $inventory->quantity -= $quantity;
                                            $inventory->save();

                                            // Preserve allocation history against original requested item
                                            PurchaseRequisitionItemFulfillment::create([
                                                'purchase_requisition_item_id' => $item->id,
                                                'product_id' => $productId,
                                                'quantity' => $quantity,
                                                'created_by' => Auth::id(),
                                            ]);
                                        }

                                        // Save approval quantity without changing original product_id
                                        $item->approved_quantity = $approvedQuantity;
                                        $item->approved_price = $item->purchase_price ?? 0;
                                        $item->total_price = $approvedQuantity * ($item->approved_price ?? 0);
                                        $item->save();
                                    }

                                    // Mark approved only after all stock deductions succeed
                                    $requisition->update([
                                        'status' => 'approved',
                                        'approved_by' => Auth::id(),
                                        'approved_at' => now(),
                                    ]);
                                });

                                return;
                            }

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
                                        'store_id' => $record->store_id,
                                        'billable_id' => $vendorId,
                                        'billable_type' => Vendor::class,
                                        'document_date' => now(),
                                        'status' => 'pending',
                                        'notes' => $record->notes,
                                        'created_by' => Auth::id(),
                                        // ✅ LINK TO PURCHASE REQUISITION
                                        'purchase_req_to_purchase_order_no' => $record->id,
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
                                    'store_id' => $record->store_id,
                                    'billable_id' => $fromStoreId,
                                    'billable_type' => Store::class,
                                    'destination_store_id' => $toStoreId,
                                    'document_date' => now(),
                                    'status' => 'pending',
                                    'notes' => $record->notes,
                                    'created_by' => Auth::id(),
                                    // ✅ LINK TO PURCHASE REQUISITION
                                    'purchase_req_to_purchase_order_no' => $record->id,
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
                ])->dropdown()->tooltip('More actions')
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
                                        'consume' => 'Consume Available Stock (Original / Linked Products)',
                                    ])
                                    ->required()
                                    ->reactive(),

                                Select::make('source_store_id')
                                    ->label('Source Store (for transfer)')
                                    ->options(Store::pluck('name', 'id'))
                                    ->visible(fn($get) => $get('method') === 'transfer')
                                    ->required(fn($get) => $get('method') === 'transfer'),

                                Repeater::make('products')
                                    ->label('Spare Parts to Purchase')
                                    ->visible(fn($get) => $get('method') === 'purchase')
                                    ->default($merged) // 👈 load merged products
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('product_name')->disabled()->label('Spare Parts')->dehydrated(true),
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
                                        'store_id' => $firstReq->store_id,
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
                                    // Source branch
                                    'store_id' => $data['source_store_id'],
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
