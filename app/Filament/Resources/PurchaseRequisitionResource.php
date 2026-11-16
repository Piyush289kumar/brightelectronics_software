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
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

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
                            modifyQueryUsing: fn(Builder $query) => $query
                                ->where('status', 'Pending')
                                ->orderByDesc('id')
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
                Tables\Columns\TextColumn::make('store.name')->label('Branch')->formatStateUsing(fn($state, $record) => $record->store ? "{$record->store->name} ({$record->store->code})" : '-')->searchable(['store.name', 'store.code'])->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('jobCard.job_id')->label('Job Card')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('items.fulfillments.product.name')
                    ->label('Selected Purchase Products')
                    ->listWithLineBreaks()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('requester.name')->label('Requested By'),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Branch')
                    ->relationship(
                        name: 'store',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn($query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn($record) => "{$record->name} ({$record->code})"
                    )
                    ->searchable(['name', 'code'])
                    ->preload(),
            ])
            ->actions([

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->disabled(fn($record) => $record->status !== 'pending')
                        ->tooltip(
                            fn($record) => $record->status !== 'pending'
                                ? 'This requisition is already processed'
                                : null
                        )
                        ->modalHeading('Approve / Create Purchase Order')
                        ->modalWidth('7xl')
                        ->visible(fn() => Auth::user()?->hasAnyRole([
                            'Administrator',
                            'Developer',
                            'admin',
                            'Team Leader',
                            'Team Lead',
                        ]) ?? false)

                        ->form(fn(PurchaseRequisition $record) => [

                            Select::make('method')
                                ->label('Fulfillment Method')
                                ->options([
                                    'purchase' => 'Purchase from Vendor',
                                    'transfer' => 'Transfer from Another Branch',
                                ])
                                ->reactive()
                                ->required(),

                            Select::make('vendor_id')
                                ->label('Default Vendor')
                                ->options(Vendor::pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->visible(fn(Get $get) => $get('method') === 'purchase')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $items = $get('items') ?? [];

                                    foreach ($items as $index => $item) {
                                        if (empty($item['vendor_id'])) {
                                            $items[$index]['vendor_id'] = $state;
                                        }
                                    }

                                    $set('items', $items);
                                }),

                            Select::make('destination_store_id')
                                ->label('Source Branch')
                                ->options(
                                    Store::where('id', '!=', $record->store_id)
                                        ->pluck('name', 'id')
                                )
                                ->visible(fn(Get $get) => $get('method') === 'transfer')
                                ->required(fn(Get $get) => $get('method') === 'transfer'),

                            Repeater::make('items')
                                ->label('Requested Products')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->default(
                                    fn() => $record->items()
                                        ->with('product')
                                        ->get()
                                        ->map(fn($item) => [
                                            'id' => $item->id,
                                            'product_id' => $item->product_id,
                                            'product_name' => $item->product?->name
                                                . ' (' . $item->product?->barcode . ')',
                                            'quantity' => $item->quantity,
                                            'approved_quantity' => $item->approved_quantity ?? $item->quantity,
                                            'approved_price' => $item->approved_price ?? $item->purchase_price ?? 0,
                                            'approved_total' => ($item->approved_quantity
                                                ?? $item->quantity)
                                                * ($item->approved_price
                                                    ?? $item->purchase_price
                                                    ?? 0),
                                            'vendor_id' => $item->vendor_id
                                                ?? null,
                                            'allocations' => [],
                                        ])
                                        ->toArray()
                                )
                                ->schema([
                                    Forms\Components\Hidden::make('id')->dehydrated(true),
                                    Forms\Components\Hidden::make('product_id')->dehydrated(true),


                                    TextInput::make('product_name')
                                        ->label('Originally Requested Product')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpanFull(),

                                    Grid::make(4)->schema([

                                        TextInput::make('quantity')
                                            ->label('Requested Quantity')
                                            ->disabled(),


                                        TextInput::make('approved_quantity')
                                            ->label('Approved Quantity')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required(
                                                fn(Get $get) =>
                                                $get('../../method') === 'purchase'
                                                    && empty($get('allocations'))
                                            )
                                            ->visible(
                                                fn(Get $get) =>
                                                $get('../../method') === 'purchase'
                                                    && empty($get('allocations'))
                                            )
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                $price = (float) ($get('approved_price') ?? 0);
                                                $set('approved_total', (float) $state * $price);
                                            }),

                                        TextInput::make('approved_price')
                                            ->label('Unit Purchase Price')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required(
                                                fn(Get $get) =>
                                                $get('../../method') === 'purchase'
                                                    && empty($get('allocations'))
                                            )
                                            ->visible(
                                                fn(Get $get) =>
                                                $get('../../method') === 'purchase'
                                                    && empty($get('allocations'))
                                            )
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                $quantity = (float) ($get('approved_quantity') ?? 0);
                                                $set('approved_total', $quantity * (float) $state);
                                            }),

                                        TextInput::make('approved_total')
                                            ->label('Total Amount')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->visible(
                                                fn(Get $get) =>
                                                $get('../../method') === 'purchase'
                                                    && empty($get('allocations'))
                                            ),


                                        Select::make('vendor_id')
                                            ->label('Vendor')
                                            ->options(Vendor::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn(Get $get) => $get('../../method') === 'purchase')
                                            ->required(fn(Get $get) => $get('../../method') === 'purchase'),
                                    ]),

                                    // Show original product and linked product stock.
                                    Forms\Components\Placeholder::make('linked_products_stock')
                                        ->label('Original & Linked Product Stock')
                                        ->content(function (Get $get) use ($record) {

                                            $productId = $get('product_id');

                                            if (! $productId) {
                                                return 'Product not found.';
                                            }

                                            $originalProduct = Product::find($productId);

                                            if (! $originalProduct) {
                                                return 'Product not found.';
                                            }

                                            $products = $originalProduct->allLinkedProducts()
                                                ->push($originalProduct)
                                                ->unique('id');

                                            $stock = StoreInventory::query()
                                                ->where('store_id', $record->store_id)
                                                ->whereIn('product_id', $products->pluck('id'))
                                                ->pluck('quantity', 'product_id');

                                            $branchName = $record->store?->name ?? 'Requesting Branch';

                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="space-y-2">' .
                                                    '<div class="font-semibold mb-2">' . e($branchName) . ' Stock</div>' .
                                                    $products->map(function ($product) use ($stock) {

                                                        $available = (int) ($stock[$product->id] ?? 0);

                                                        return '<div class="flex justify-between border-b py-2">' .
                                                            '<span>' . e($product->name) .
                                                            ' (' . e($product->barcode) . ')</span>' .
                                                            '<strong>' . $available . '</strong>' .
                                                            '</div>';
                                                    })->implode('') .
                                                    '</div>'
                                            );
                                        })
                                        ->visible(fn(Get $get) => $get('../../method') === 'purchase')
                                        ->columnSpanFull(),

                                    // Admin selects actual products for Purchase Order.
                                    Repeater::make('allocations')
                                        ->label('Select Actual Products for Purchase Order')
                                        ->visible(fn(Get $get) => $get('../../method') === 'purchase')
                                        ->addActionLabel('Add Linked Product')
                                        ->default([])
                                        ->schema([
                                            Select::make('product_id')
                                                ->label('Select Original / Linked Product')
                                                ->required()
                                                ->searchable()
                                                ->options(function (Get $get) use ($record) {
                                                    $requestedProductId = $get('../../product_id');

                                                    if (! $requestedProductId) {
                                                        return [];
                                                    }

                                                    $originalProduct = Product::find($requestedProductId);

                                                    if (! $originalProduct) {
                                                        return [];
                                                    }

                                                    $productIds = $originalProduct->allLinkedProducts()
                                                        ->pluck('id')
                                                        ->push($originalProduct->id)
                                                        ->unique();

                                                    $products = Product::whereIn('id', $productIds)
                                                        ->where('is_active', true)
                                                        ->get();

                                                    $stock = StoreInventory::where('store_id', $record->store_id)
                                                        ->whereIn('product_id', $productIds)
                                                        ->pluck('quantity', 'product_id');

                                                    return $products->mapWithKeys(function ($product) use ($stock) {
                                                        $available = (int) ($stock[$product->id] ?? 0);

                                                        return [
                                                            $product->id =>
                                                            "{$product->name} ({$product->barcode}) | Branch Stock: {$available}",
                                                        ];
                                                    })->toArray();
                                                })
                                                ->live()
                                                ->distinct()
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                    $product = Product::find($state);

                                                    $price = (float) ($product?->purchase_price ?? 0);
                                                    $quantity = (float) ($get('quantity') ?? 0);

                                                    $set('unit_price', $price);
                                                    $set('total_price', $quantity * $price);
                                                }),

                                            TextInput::make('quantity')
                                                ->label('Purchase Quantity')
                                                ->numeric()
                                                ->minValue(1)
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                    $price = (float) ($get('unit_price') ?? 0);

                                                    $set('total_price', (float) $state * $price);
                                                }),

                                            TextInput::make('unit_price')
                                                ->label('Unit Price')
                                                ->numeric()
                                                ->minValue(0)
                                                ->required()
                                                ->default(0)
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                    $quantity = (float) ($get('quantity') ?? 0);

                                                    $set('total_price', $quantity * (float) $state);
                                                }),

                                            TextInput::make('total_price')
                                                ->label('Total Price')
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated(true)
                                                ->default(0),
                                        ])
                                        ->columns(4)
                                        ->columnSpanFull(),

                                ])
                                ->columns(1)
                                ->columnSpanFull(),
                        ])

                        ->action(function (PurchaseRequisition $record, array $data) {
                            try {
                                DB::transaction(function () use ($record, $data) {

                                    Log::info('Purchase requisition approval started', [
                                        'requisition_id' => $record->id,
                                        'method' => $data['method'] ?? null,
                                        'submitted_data' => $data,
                                    ]);

                                    $requisition = PurchaseRequisition::query()
                                        ->lockForUpdate()
                                        ->findOrFail($record->id);

                                    if ($requisition->status !== 'pending') {
                                        throw ValidationException::withMessages([
                                            'method' => 'This requisition has already been processed.',
                                        ]);
                                    }

                                    $items = $requisition->items()
                                        ->with('product')
                                        ->get();

                                    $submittedRows = collect($data['items'] ?? [])->values();

                                    $submittedItems = $submittedRows
                                        ->filter(fn($row) => ! empty($row['id']))
                                        ->keyBy(fn($row) => (int) $row['id']);

                                    if ($submittedItems->count() !== $items->count()) {
                                        throw ValidationException::withMessages([
                                            'method' => 'Requisition item IDs are missing or incomplete. Please reopen the approval form.',
                                        ]);
                                    }


                                    if (($data['method'] ?? null) === 'purchase') {

                                        $purchaseOrderItems = [];

                                        foreach ($items as $item) {

                                            $row = $submittedItems->get($item->id);

                                            if (! $row) {
                                                throw ValidationException::withMessages([
                                                    'method' => "Missing details for {$item->product?->name}.",
                                                ]);
                                            }

                                            $vendorId = $row['vendor_id'] ?? $data['vendor_id'] ?? null;

                                            if (! $vendorId || ! Vendor::whereKey($vendorId)->exists()) {
                                                throw ValidationException::withMessages([
                                                    'method' => "Please select a valid vendor for {$item->product?->name}.",
                                                ]);
                                            }

                                            $requestedQty = (float) $item->quantity;

                                            $allocations = collect($row['allocations'] ?? [])
                                                ->filter(fn($allocation) => ! empty($allocation['product_id']))
                                                ->values();

                                            $fulfillmentRows = [];
                                            $totalQty = 0;
                                            $totalAmount = 0;

                                            // CASE 1: No linked/original product allocations.
                                            // Use normal Approved Quantity and Unit Purchase Price.
                                            if ($allocations->isEmpty()) {

                                                $approvedQty = (float) ($row['approved_quantity'] ?? 0);
                                                $unitPrice = (float) ($row['approved_price'] ?? 0);

                                                if ($approvedQty < $requestedQty || $approvedQty <= 0) {
                                                    throw ValidationException::withMessages([
                                                        'method' => "For {$item->product?->name}, approved quantity must be at least {$requestedQty}.",
                                                    ]);
                                                }

                                                if ($unitPrice < 0) {
                                                    throw ValidationException::withMessages([
                                                        'method' => 'Unit purchase price cannot be negative.',
                                                    ]);
                                                }

                                                $product = Product::query()
                                                    ->where('is_active', true)
                                                    ->find($item->product_id);

                                                if (! $product) {
                                                    throw ValidationException::withMessages([
                                                        'method' => "Original product {$item->product?->name} is inactive or unavailable.",
                                                    ]);
                                                }

                                                $totalQty = $approvedQty;
                                                $totalAmount = $approvedQty * $unitPrice;

                                                $fulfillmentRows[] = [
                                                    'product_id' => $product->id,
                                                    'quantity' => $approvedQty,
                                                ];

                                                $purchaseOrderItems[$vendorId][] = [
                                                    'product_id' => $product->id,
                                                    'description' => $product->name,
                                                    'quantity' => $approvedQty,
                                                    'unit_price' => $unitPrice,
                                                    'total_amount' => $totalAmount,
                                                ];
                                            } else {

                                                // CASE 2: Original + linked product allocations.
                                                $allowedProductIds = $item->product
                                                    ->allLinkedProducts()
                                                    ->pluck('id')
                                                    ->push($item->product_id)
                                                    ->map(fn($id) => (int) $id)
                                                    ->unique();

                                                foreach ($allocations as $allocation) {

                                                    $productId = (int) ($allocation['product_id'] ?? 0);
                                                    $quantity = (float) ($allocation['quantity'] ?? 0);
                                                    $unitPrice = (float) ($allocation['unit_price'] ?? 0);

                                                    if (! $allowedProductIds->contains($productId)) {
                                                        throw ValidationException::withMessages([
                                                            'method' => 'Selected product is not linked to the requested product.',
                                                        ]);
                                                    }

                                                    if ($quantity <= 0 || $unitPrice < 0) {
                                                        throw ValidationException::withMessages([
                                                            'method' => 'Enter valid allocation quantity and unit price.',
                                                        ]);
                                                    }

                                                    $product = Product::query()
                                                        ->where('is_active', true)
                                                        ->find($productId);

                                                    if (! $product) {
                                                        throw ValidationException::withMessages([
                                                            'method' => 'Selected product is inactive or unavailable.',
                                                        ]);
                                                    }

                                                    $lineTotal = $quantity * $unitPrice;

                                                    $totalQty += $quantity;
                                                    $totalAmount += $lineTotal;

                                                    $fulfillmentRows[] = [
                                                        'product_id' => $productId,
                                                        'quantity' => $quantity,
                                                    ];

                                                    $purchaseOrderItems[$vendorId][] = [
                                                        'product_id' => $productId,
                                                        'description' => $product->name,
                                                        'quantity' => $quantity,
                                                        'unit_price' => $unitPrice,
                                                        'total_amount' => $lineTotal,
                                                    ];
                                                }

                                                if ($totalQty < $requestedQty) {
                                                    throw ValidationException::withMessages([
                                                        'method' => "For {$item->product?->name}, requested {$requestedQty}, but allocated {$totalQty}. Allocate at least {$requestedQty}.",
                                                    ]);
                                                }
                                            }

                                            // Save requisition item summary.
                                            $item->approved_quantity = $totalQty;

                                            $item->approved_price = $totalQty > 0
                                                ? $totalAmount / $totalQty
                                                : 0;

                                            $item->total_price = $totalAmount;
                                            $item->vendor_id = $vendorId;
                                            $item->save();

                                            // Save original/linked product fulfillment records.
                                            $item->fulfillments()->delete();
                                            $item->fulfillments()->createMany($fulfillmentRows);
                                        }

                                        // Create Purchase Orders grouped by vendor.
                                        foreach ($purchaseOrderItems as $vendorId => $poItems) {

                                            $invoice = Invoice::create([
                                                'document_type' => 'purchase_order',
                                                'store_id' => $requisition->store_id,
                                                'billable_id' => $vendorId,
                                                'billable_type' => Vendor::class,
                                                'document_date' => now(),
                                                'status' => 'pending',
                                                'notes' => $requisition->notes,
                                                'created_by' => Auth::id(),
                                                'purchase_req_to_purchase_order_no' => $requisition->id,
                                            ]);

                                            $invoice->items()->createMany($poItems);

                                            $invoice->update([
                                                'total_amount' => collect($poItems)->sum('total_amount'),
                                            ]);

                                            Log::info('Purchase Order created', [
                                                'invoice_id' => $invoice->id,
                                                'requisition_id' => $requisition->id,
                                                'vendor_id' => $vendorId,
                                                'total_amount' => collect($poItems)->sum('total_amount'),
                                            ]);
                                        }
                                    } elseif (($data['method'] ?? null) === 'transfer') {

                                        $sourceStoreId = $data['destination_store_id'] ?? null;

                                        if (! $sourceStoreId || $sourceStoreId == $requisition->store_id) {
                                            throw ValidationException::withMessages([
                                                'method' => 'Select a valid source branch.',
                                            ]);
                                        }

                                        foreach ($items as $item) {
                                            $row = $submittedItems->get($item->id);
                                            $approvedQty = (float) ($row['approved_quantity'] ?? 0);

                                            if ($approvedQty <= 0) {
                                                throw ValidationException::withMessages([
                                                    'method' => "Invalid quantity for {$item->product?->name}.",
                                                ]);
                                            }

                                            $item->approved_quantity = $approvedQty;
                                            $item->save();
                                        }

                                        $invoice = Invoice::create([
                                            'document_type' => 'transfer_order',
                                            'store_id' => $requisition->store_id,
                                            'billable_id' => $sourceStoreId,
                                            'billable_type' => Store::class,
                                            'destination_store_id' => $requisition->store_id,
                                            'document_date' => now(),
                                            'status' => 'pending',
                                            'notes' => $requisition->notes,
                                            'created_by' => Auth::id(),
                                            'purchase_req_to_purchase_order_no' => $requisition->id,
                                        ]);

                                        $transferItems = $items->map(fn($item) => [
                                            'product_id' => $item->product_id,
                                            'description' => $item->product?->name,
                                            'quantity' => $item->approved_quantity,
                                            'unit_price' => 0,
                                            'total_amount' => 0,
                                        ])->all();

                                        $invoice->items()->createMany($transferItems);
                                        $invoice->update(['total_amount' => 0]);
                                    } else {
                                        throw ValidationException::withMessages([
                                            'method' => 'Invalid fulfillment method.',
                                        ]);
                                    }

                                    $requisition->update([
                                        'status' => 'approved',
                                        'approved_by' => Auth::id(),
                                        'approved_at' => now(),
                                    ]);
                                });

                                Notification::make()
                                    ->title('Requisition approved successfully')
                                    ->body('Purchase Order / Transfer Order has been created.')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {
                                throw $e;
                            } catch (Throwable $e) {
                                Log::error('Purchase requisition approval failed', [
                                    'requisition_id' => $record->id,
                                    'message' => $e->getMessage(),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Approval failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()->label('Reject'),
                ])->dropdown()->tooltip('More actions')
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([


                    Tables\Actions\BulkAction::make('bulkFulfill')
                        ->label('Bulk Approve')
                        ->icon('heroicon-o-shopping-cart')
                        ->color('success')
                        ->disabled(function ($livewire): bool {
                            $records = $livewire->getSelectedTableRecords();

                            return $records->pluck('store_id')->unique()->count() > 1;
                        })
                        ->modalHeading('Bulk Approve Requisitions & Create Purchase Order')
                        ->modalWidth('7xl')
                        ->modalCancelActionLabel('Cancel')
                        ->form(function (Collection $records) {

                            $records->loadMissing(['items.product', 'store']);

                            if ($records->isEmpty()) {
                                throw ValidationException::withMessages([
                                    'items' => 'Select at least one requisition.',
                                ]);
                            }

                            if ($records->contains(fn($req) => $req->status !== 'pending')) {
                                Notification::make()
                                    ->title('Invalid Requisition Status')
                                    ->body('Only pending requisitions can be approved.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return [];
                            }

                            $storeId = $records->first()->store_id;

                            // Keep every requisition item separate.
                            $formItems = $records->flatMap(function ($req) {

                                return $req->items->map(function ($item) use ($req) {

                                    return [
                                        'id' => $item->id,
                                        'requisition_id' => $req->id,
                                        'product_id' => $item->product_id,

                                        'requisition_number' => '#' . $req->id,

                                        'product_name' => $item->product?->name
                                            . ' (' . $item->product?->barcode . ')',

                                        'quantity' => (float) $item->quantity,

                                        'approved_quantity' => (float) (
                                            $item->approved_quantity ?? $item->quantity
                                        ),

                                        'approved_price' => (float) (
                                            $item->approved_price
                                            ?? $item->purchase_price
                                            ?? 0
                                        ),

                                        'allocations' => [],
                                    ];
                                });
                            })->values()->toArray();

                            return [

                                Forms\Components\Placeholder::make('branch_info')
                                    ->label('Selected Branch')
                                    ->content($records->first()->store?->name ?? 'Branch'),

                                Forms\Components\Placeholder::make('requisition_info')
                                    ->label('Selected Requisitions')
                                    ->content(
                                        $records->count() . ' requisition(s): ' .
                                            $records->pluck('id')
                                            ->map(fn($id) => '#' . $id)
                                            ->implode(', ')
                                    ),

                                Select::make('vendor_id')
                                    ->label('Purchase Order Vendor')
                                    ->options(Vendor::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Repeater::make('items')
                                    ->label('Requisition Items & Product Allocations')
                                    ->default($formItems)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->schema([

                                        Forms\Components\Hidden::make('id')
                                            ->dehydrated(true),

                                        Forms\Components\Hidden::make('requisition_id')
                                            ->dehydrated(true),

                                        Forms\Components\Hidden::make('product_id')
                                            ->dehydrated(true),

                                        Forms\Components\Placeholder::make('requisition_number')
                                            ->label('Requisition')
                                            ->content(fn(Get $get) => $get('requisition_number')),

                                        TextInput::make('product_name')
                                            ->label('Originally Requested Product')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Grid::make(3)->schema([

                                            TextInput::make('quantity')
                                                ->label('Requested Quantity')
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated(true),

                                            TextInput::make('approved_quantity')
                                                ->label('Approved Quantity (No Allocations)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->required(fn(Get $get) => empty($get('allocations')))
                                                ->visible(fn(Get $get) => empty($get('allocations'))),

                                            TextInput::make('approved_price')
                                                ->label('Unit Price (No Allocations)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->required(fn(Get $get) => empty($get('allocations')))
                                                ->visible(fn(Get $get) => empty($get('allocations'))),

                                        ]),

                                        Forms\Components\Placeholder::make('linked_products_stock')
                                            ->label('Original & Linked Product Branch Stock')
                                            ->content(function (Get $get) use ($storeId) {

                                                $productId = $get('product_id');

                                                $originalProduct = Product::find($productId);

                                                if (! $originalProduct) {
                                                    return 'Original product not found.';
                                                }

                                                $products = $originalProduct->allLinkedProducts()
                                                    ->push($originalProduct)
                                                    ->unique('id');

                                                $stock = StoreInventory::query()
                                                    ->where('store_id', $storeId)
                                                    ->whereIn('product_id', $products->pluck('id'))
                                                    ->pluck('quantity', 'product_id');

                                                return new \Illuminate\Support\HtmlString(
                                                    '<div class="space-y-2">' .
                                                        $products->map(function ($product) use ($stock) {

                                                            $available = (float) (
                                                                $stock[$product->id] ?? 0
                                                            );

                                                            return '<div class="flex justify-between border-b py-2">' .
                                                                '<span>' . e($product->name) .
                                                                ' (' . e($product->barcode) . ')</span>' .
                                                                '<strong>Stock: ' . $available . '</strong>' .
                                                                '</div>';
                                                        })->implode('') .
                                                        '</div>'
                                                );
                                            })
                                            ->columnSpanFull(),

                                        Repeater::make('allocations')
                                            ->label('Select Original / Linked Products for This Request')
                                            ->addActionLabel('Add Linked Product')
                                            ->default([])
                                            ->schema([

                                                Select::make('product_id')
                                                    ->label('Actual Product')
                                                    ->required()
                                                    ->searchable()
                                                    ->options(function (Get $get) use ($storeId) {

                                                        $requestedProductId = $get('../../product_id');

                                                        $originalProduct = Product::find(
                                                            $requestedProductId
                                                        );

                                                        if (! $originalProduct) {
                                                            return [];
                                                        }

                                                        $productIds = $originalProduct
                                                            ->allLinkedProducts()
                                                            ->pluck('id')
                                                            ->push($originalProduct->id)
                                                            ->unique();

                                                        $products = Product::query()
                                                            ->whereIn('id', $productIds)
                                                            ->where('is_active', true)
                                                            ->get();

                                                        $stock = StoreInventory::query()
                                                            ->where('store_id', $storeId)
                                                            ->whereIn('product_id', $productIds)
                                                            ->pluck('quantity', 'product_id');

                                                        return $products->mapWithKeys(
                                                            function ($product) use ($stock) {

                                                                $available = (float) (
                                                                    $stock[$product->id] ?? 0
                                                                );

                                                                return [
                                                                    $product->id =>
                                                                    "{$product->name} ({$product->barcode}) | Stock: {$available}",
                                                                ];
                                                            }
                                                        )->toArray();
                                                    })
                                                    ->live()
                                                    ->distinct()
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->afterStateUpdated(function (
                                                        $state,
                                                        callable $get,
                                                        callable $set
                                                    ) {

                                                        $product = Product::find($state);

                                                        $price = (float) (
                                                            $product?->purchase_price ?? 0
                                                        );

                                                        $quantity = (float) (
                                                            $get('quantity') ?? 0
                                                        );

                                                        $set('unit_price', $price);
                                                        $set('total_price', $quantity * $price);
                                                    }),

                                                TextInput::make('quantity')
                                                    ->label('Purchase Quantity')
                                                    ->numeric()
                                                    ->minValue(0.01)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function (
                                                        $state,
                                                        callable $get,
                                                        callable $set
                                                    ) {

                                                        $price = (float) (
                                                            $get('unit_price') ?? 0
                                                        );

                                                        $set(
                                                            'total_price',
                                                            (float) $state * $price
                                                        );
                                                    }),

                                                TextInput::make('unit_price')
                                                    ->label('Unit Purchase Price')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->required()
                                                    ->default(0)
                                                    ->live()
                                                    ->afterStateUpdated(function (
                                                        $state,
                                                        callable $get,
                                                        callable $set
                                                    ) {

                                                        $quantity = (float) (
                                                            $get('quantity') ?? 0
                                                        );

                                                        $set(
                                                            'total_price',
                                                            $quantity * (float) $state
                                                        );
                                                    }),

                                                TextInput::make('total_price')
                                                    ->label('Total Amount')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(true)
                                                    ->default(0),

                                            ])
                                            ->columns(4)
                                            ->columnSpanFull(),

                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ];
                        })

                        ->action(function (Collection $records, array $data) {

                            // 1. Check that all selected requisitions belong to one branch.
                            $storeIds = $records->pluck('store_id')->unique();

                            if ($storeIds->count() !== 1) {
                                Notification::make()
                                    ->title('Multiple Branches Selected')
                                    ->body('Please select requisitions from one branch only.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            // 2. Check pending status.
                            if ($records->contains(fn($req) => $req->status !== 'pending')) {
                                Notification::make()
                                    ->title('Invalid Requisition Status')
                                    ->body('Only pending requisitions can be approved.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            try {

                                DB::transaction(function () use ($records, $data) {

                                    // Lock selected requisitions.
                                    $requisitions = PurchaseRequisition::query()
                                        ->whereIn('id', $records->pluck('id'))
                                        ->orderBy('id')
                                        ->lockForUpdate()
                                        ->with(['items.product'])
                                        ->get();

                                    if ($requisitions->count() !== $records->count()) {
                                        throw ValidationException::withMessages([
                                            'items' => 'Some selected requisitions were not found.',
                                        ]);
                                    }

                                    if ($requisitions->pluck('store_id')->unique()->count() !== 1) {
                                        throw ValidationException::withMessages([
                                            'vendor_id' => 'All requisitions must belong to the same branch.',
                                        ]);
                                    }

                                    if ($requisitions->contains(
                                        fn($req) => $req->status !== 'pending'
                                    )) {
                                        throw ValidationException::withMessages([
                                            'vendor_id' => 'One or more requisitions have already been processed.',
                                        ]);
                                    }

                                    $vendorId = $data['vendor_id'] ?? null;

                                    if (! $vendorId || ! Vendor::whereKey($vendorId)->exists()) {
                                        throw ValidationException::withMessages([
                                            'vendor_id' => 'Please select a valid vendor.',
                                        ]);
                                    }

                                    // Match submitted data using the actual requisition item ID.
                                    $submittedItems = collect($data['items'] ?? [])
                                        ->filter(fn($row) => ! empty($row['id']))
                                        ->keyBy(fn($row) => (int) $row['id']);

                                    $allItems = $requisitions->flatMap(
                                        fn($req) => $req->items
                                    );

                                    if ($submittedItems->count() !== $allItems->count()) {
                                        throw ValidationException::withMessages([
                                            'items' => 'Requisition item details are missing. Reopen the bulk approval form.',
                                        ]);
                                    }

                                    // Every allocation remains a separate PO line.
                                    $purchaseOrderItems = [];

                                    foreach ($requisitions as $requisition) {

                                        foreach ($requisition->items as $item) {

                                            $row = $submittedItems->get((int) $item->id);

                                            if (! $row) {
                                                throw ValidationException::withMessages([
                                                    'items' => "Missing details for {$item->product?->name}.",
                                                ]);
                                            }

                                            $requestedQty = (float) $item->quantity;

                                            $allocations = collect($row['allocations'] ?? [])
                                                ->filter(
                                                    fn($allocation) =>
                                                    ! empty($allocation['product_id'])
                                                )
                                                ->values();

                                            $fulfillmentRows = [];

                                            $totalQty = 0;
                                            $totalAmount = 0;

                                            /*
                                            * CASE 1:
                                            * No allocations selected.
                                            * Purchase the originally requested product.
                                            */
                                            if ($allocations->isEmpty()) {

                                                $approvedQty = (float) (
                                                    $row['approved_quantity'] ?? 0
                                                );

                                                $unitPrice = (float) (
                                                    $row['approved_price'] ?? 0
                                                );

                                                if ($approvedQty < $requestedQty || $approvedQty <= 0) {
                                                    throw ValidationException::withMessages([
                                                        'items' => "For {$item->product?->name}, approved quantity must be at least {$requestedQty}.",
                                                    ]);
                                                }

                                                if ($unitPrice < 0) {
                                                    throw ValidationException::withMessages([
                                                        'items' => 'Unit purchase price cannot be negative.',
                                                    ]);
                                                }

                                                $product = Product::query()
                                                    ->where('is_active', true)
                                                    ->find($item->product_id);

                                                if (! $product) {
                                                    throw ValidationException::withMessages([
                                                        'items' => "Original product {$item->product?->name} is inactive or unavailable.",
                                                    ]);
                                                }

                                                $totalQty = $approvedQty;
                                                $totalAmount = $approvedQty * $unitPrice;

                                                $fulfillmentRows[] = [
                                                    'product_id' => $product->id,
                                                    'quantity' => $approvedQty,
                                                ];

                                                $purchaseOrderItems[] = [
                                                    'product_id' => $product->id,
                                                    'description' => $product->name,
                                                    'quantity' => $approvedQty,
                                                    'unit_price' => $unitPrice,
                                                    'total_amount' => $totalAmount,
                                                ];
                                            } else {

                                                /*
                                                * CASE 2:
                                                * Original + linked product allocations.
                                                */

                                                if (! $item->product) {
                                                    throw ValidationException::withMessages([
                                                        'items' => 'Original requested product was not found.',
                                                    ]);
                                                }

                                                $allowedProductIds = $item->product
                                                    ->allLinkedProducts()
                                                    ->pluck('id')
                                                    ->push($item->product_id)
                                                    ->map(fn($id) => (int) $id)
                                                    ->unique();

                                                foreach ($allocations as $allocation) {

                                                    $productId = (int) (
                                                        $allocation['product_id'] ?? 0
                                                    );

                                                    $quantity = (float) (
                                                        $allocation['quantity'] ?? 0
                                                    );

                                                    $unitPrice = (float) (
                                                        $allocation['unit_price'] ?? 0
                                                    );

                                                    if (! $allowedProductIds->contains($productId)) {
                                                        throw ValidationException::withMessages([
                                                            'items' => "Selected product is not linked to {$item->product?->name}.",
                                                        ]);
                                                    }

                                                    if ($quantity <= 0 || $unitPrice < 0) {
                                                        throw ValidationException::withMessages([
                                                            'items' => 'Enter valid allocation quantity and unit price.',
                                                        ]);
                                                    }

                                                    $product = Product::query()
                                                        ->where('is_active', true)
                                                        ->find($productId);

                                                    if (! $product) {
                                                        throw ValidationException::withMessages([
                                                            'items' => 'Selected original or linked product is inactive or unavailable.',
                                                        ]);
                                                    }

                                                    $lineTotal = $quantity * $unitPrice;

                                                    $totalQty += $quantity;
                                                    $totalAmount += $lineTotal;

                                                    // Save each selected actual product.
                                                    $fulfillmentRows[] = [
                                                        'product_id' => $productId,
                                                        'quantity' => $quantity,
                                                    ];

                                                    // Keep each allocation's own price.
                                                    $purchaseOrderItems[] = [
                                                        'product_id' => $productId,
                                                        'description' => $product->name,
                                                        'quantity' => $quantity,
                                                        'unit_price' => $unitPrice,
                                                        'total_amount' => $lineTotal,
                                                    ];
                                                }

                                                if ($totalQty < $requestedQty) {
                                                    throw ValidationException::withMessages([
                                                        'items' => "For {$item->product?->name}, requested {$requestedQty}, but allocated {$totalQty}. Allocate at least {$requestedQty}.",
                                                    ]);
                                                }
                                            }

                                            // Update the original requisition item summary.
                                            $item->approved_quantity = $totalQty;

                                            $item->approved_price = $totalQty > 0
                                                ? $totalAmount / $totalQty
                                                : 0;

                                            $item->total_price = $totalAmount;
                                            $item->vendor_id = $vendorId;
                                            $item->save();

                                            // Replace previous fulfillment rows.
                                            $item->fulfillments()->delete();

                                            $item->fulfillments()->createMany(
                                                $fulfillmentRows
                                            );
                                        }
                                    }

                                    if (empty($purchaseOrderItems)) {
                                        throw ValidationException::withMessages([
                                            'items' => 'No Purchase Order products were generated.',
                                        ]);
                                    }

                                    // Create ONE PO for the selected vendor and branch.
                                    $requisitionIds = $requisitions
                                        ->pluck('id')
                                        ->map(fn($id) => '#' . $id)
                                        ->implode(', ');

                                    $invoice = Invoice::create([
                                        'document_type' => 'purchase_order',
                                        'store_id' => $requisitions->first()->store_id,
                                        'billable_id' => $vendorId,
                                        'billable_type' => Vendor::class,
                                        'document_date' => now(),
                                        'status' => 'pending',
                                        'notes' => "Bulk requisition purchase. Requisitions: {$requisitionIds}",
                                        'created_by' => Auth::id(),
                                    ]);

                                    $invoice->items()->createMany($purchaseOrderItems);

                                    $grandTotal = collect($purchaseOrderItems)
                                        ->sum('total_amount');

                                    $invoice->update([
                                        'total_amount' => $grandTotal,
                                    ]);

                                    // Approve all requisitions only after PO creation.
                                    foreach ($requisitions as $requisition) {

                                        $requisition->update([
                                            'status' => 'approved',
                                            'approved_by' => Auth::id(),
                                            'approved_at' => now(),
                                        ]);
                                    }

                                    Log::info('Bulk Purchase Order created', [
                                        'invoice_id' => $invoice->id,
                                        'requisition_ids' => $requisitions->pluck('id')->all(),
                                        'vendor_id' => $vendorId,
                                        'store_id' => $requisitions->first()->store_id,
                                        'total_amount' => $grandTotal,
                                    ]);
                                });

                                Notification::make()
                                    ->title('Bulk approval successful')
                                    ->body('All selected requisitions were approved and one Purchase Order was created.')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {

                                Notification::make()
                                    ->title('Approval Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            } catch (Throwable $e) {

                                Log::error('Bulk requisition approval failed', [
                                    'message' => $e->getMessage(),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Something Went Wrong')
                                    ->body('Unable to complete bulk approval. Please try again.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
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
