<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Products & Categories';

    protected static ?string $label = 'Spare Parts';
    protected static ?int $navigationSort = 15;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('sku')
                            ->disabled()
                            ->dehydrated() // Save to DB
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('barcode')
                            ->label('Part Code/Number')
                            ->dehydrated()
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('linkedProducts')
                            ->label('Linked Products')
                            ->multiple()
                            ->relationship('linkedProducts')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->barcode} - {$record->name}")
                            ->preload()
                            ->helperText('Select products that are related to this product'),

                        Forms\Components\Select::make('unit_id')
                            ->label('Unit')
                            ->relationship('unit', 'name')
                            ->nullable(),

                        Forms\Components\Select::make('brand_id')
                            ->label('Brand')
                            ->relationship('brand', 'name')
                            ->nullable(),

                    ])->columns(3),

                Forms\Components\Section::make('Category & Tax')
                    ->schema([
                        // Level 1: Main Category
                        Forms\Components\Select::make('category_level_1')
                            ->label('Main Category')
                            ->options(
                                Category::whereNull('parent_id')->pluck('name', 'id')
                            )
                            ->reactive()
                            ->afterStateHydrated(function ($set, $get, $record) {
                                if (!$record?->category_id) {
                                    return;
                                }

                                $category = Category::find($record->category_id);

                                if ($category?->parent?->parent) {
                                    // Level 3 selected
                                    $set('category_level_1', $category->parent->parent->id);
                                    $set('category_level_2', $category->parent->id);
                                    $set('category_level_3', $category->id);
                                } elseif ($category?->parent) {
                                    // Level 2 selected
                                    $set('category_level_1', $category->parent->id);
                                    $set('category_level_2', $category->id);
                                } else {
                                    // Level 1 selected
                                    $set('category_level_1', $category->id);
                                }
                            })
                            ->afterStateUpdated(fn($set) => $set('category_level_2', null)),

                        // Level 2: Sub Category (only shows if Level 1 has children)
                        Forms\Components\Select::make('category_level_2')
                            ->label('Sub Category')
                            ->options(function (callable $get) {
                                $parentId = $get('category_level_1');
                                if (!$parentId)
                                    return [];
                                return Category::where('parent_id', $parentId)
                                    ->pluck('name', 'id');
                            })
                            ->hidden(function (callable $get) {
                                $parentId = $get('category_level_1');
                                return !Category::where('parent_id', $parentId)->exists();
                            })
                            ->reactive()
                            ->afterStateUpdated(fn(callable $set) => $set('category_level_3', null)),

                        // Level 3: Sub Sub Category (only shows if Level 2 has children)
                        Forms\Components\Select::make('category_level_3')
                            ->label('Sub Sub Category')
                            ->options(function (callable $get) {
                                $parentId = $get('category_level_2');
                                if (!$parentId)
                                    return [];
                                return Category::where('parent_id', $parentId)
                                    ->pluck('name', 'id');
                            })
                            ->hidden(function (callable $get) {
                                $parentId = $get('category_level_2');
                                return !$parentId || !Category::where('parent_id', $parentId)->exists();
                            })
                            ->reactive(),

                        // Hidden actual field to store selected category ID
                        Forms\Components\Hidden::make('category_id')
                            ->dehydrateStateUsing(function (callable $get) {
                                return $get('category_level_3')
                                    ?? $get('category_level_2')
                                    ?? $get('category_level_1');
                            }),

                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('hsn_code')->label('Model No.')
                                    ->maxLength(8),

                                Forms\Components\Select::make('tax_slab_id')
                                    ->relationship('taxSlab', 'name')
                                    ->nullable(),

                                Forms\Components\TextInput::make('gst_rate')
                                    ->numeric()
                                    ->step(0.01)
                                    ->nullable(),
                            ]),
                    ])->columns(3),



                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('purchase_price')->numeric()->step(0.01)->required()->default(0.00),
                        Forms\Components\TextInput::make('selling_price')->numeric()->step(0.01)->required()->default(0.00),
                        Forms\Components\TextInput::make('mrp')->numeric()->step(0.01)->nullable()->default(0.00),
                    ])->columns(3),

                Forms\Components\Section::make('Stock Management')
                    ->schema([
                        Forms\Components\Toggle::make('track_inventory')->default(true),
                        Forms\Components\TextInput::make('min_stock')->numeric()->default(0),
                        Forms\Components\TextInput::make('max_stock')->numeric()->nullable(),
                    ])->columns(3),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')->default(true),
                        Forms\Components\FileUpload::make('image_path')->label('Product Image')
                            ->disk('public')
                            ->directory('products')
                            ->image()
                            ->nullable(),
                        Forms\Components\KeyValue::make('meta')
                            ->label('Custom Metadata')
                            ->nullable(),
                    ])->columns(3),
            ]);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        // remove linkedProducts from data so Filament doesn't try to save it in products table
        $data['linkedProducts_ids'] = $data['linkedProducts'] ?? [];
        unset($data['linkedProducts']);

        return $data;
    }

    public static function afterSave(Form $form, Product $record): void
    {
        // Apply the linking after product is saved and has an ID
        $record->syncLinks($form->getState()['linkedProducts_ids'] ?? []);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('barcode')->label('Part No.')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('hsn_code')->label('Model No.')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('sku')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('category.parent.name')
                    ->label('Category')
                    ->formatStateUsing(fn($state) => $state ?? '-')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Sub Category')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->category?->parent_id ? $state : '-'
                    )
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('selling_price')->money('INR')->sortable()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->date()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Filter by Category
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable(),

                // Filter by Brand
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable(),

                // Filter by Unit
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'symbol')
                    ->searchable(),

                // Filter by Tax Slab
                Tables\Filters\SelectFilter::make('tax_slab_id')
                    ->label('Tax Slab')
                    ->relationship('taxSlab', 'name'),

                // Active / Inactive
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                // Track Inventory
                Tables\Filters\TernaryFilter::make('track_inventory')
                    ->label('Inventory Tracking'),

                // Stock status: Out of Stock
                Tables\Filters\Filter::make('Out of Stock')
                    ->query(fn(Builder $query) => $query->where('min_stock', '>=', 'max_stock')),

                // Stock status: Low Stock
                Tables\Filters\Filter::make('Low Stock')
                    ->query(fn(Builder $query) => $query->whereColumn('min_stock', '>', 'max_stock')),

                // Price Range
                Tables\Filters\Filter::make('Price Range')
                    ->form([
                        Forms\Components\TextInput::make('min_price')->numeric(),
                        Forms\Components\TextInput::make('max_price')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['min_price'], fn($q) => $q->where('selling_price', '>=', $data['min_price']))
                            ->when($data['max_price'], fn($q) => $q->where('selling_price', '<=', $data['max_price']));
                    }),

                // GST Rate
                Tables\Filters\Filter::make('GST Rate')
                    ->form([
                        Forms\Components\TextInput::make('min_gst')->numeric(),
                        Forms\Components\TextInput::make('max_gst')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['min_gst'], fn($q) => $q->where('gst_rate', '>=', $data['min_gst']))
                            ->when($data['max_gst'], fn($q) => $q->where('gst_rate', '<=', $data['max_gst']));
                    }),

                // Created date range
                Tables\Filters\Filter::make('Created Date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Show nly active products
     */

    // public static function getEloquentQuery(): Builder
    // {
    //     return parent::getEloquentQuery()
    //         ->where('is_active', true); // Only active products
    // }



}
