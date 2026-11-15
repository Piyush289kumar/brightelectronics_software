<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreInventoryResource\Pages;
use App\Filament\Resources\StoreInventoryResource\RelationManagers;
use App\Models\Store;
use App\Models\StoreInventory;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class StoreInventoryResource extends BaseResource
{
    protected static ?string $model = StoreInventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $label = 'Branch Inventory';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 2;

    protected static function permission(string $action): string
    {
        return "{$action}_store::inventory";
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([


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

                Forms\Components\Select::make('product_id')
                    ->label('Spare Parts')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->options(function (callable $get, $record) {

                        $storeId = $get('store_id');
                        if (!$storeId) {
                            return [];
                        }

                        $query = \App\Models\Product::query()->where('is_active', true);

                        // Create mode
                        if (!$record) {
                            $query->whereDoesntHave(
                                'storeInventories',
                                fn($q) => $q->where('store_id', $storeId),
                            );
                        }
                        // Edit mode
                        else {
                            $query->where(function ($q) use ($storeId, $record) {
                                $q->whereDoesntHave(
                                    'storeInventories',
                                    fn($sub) => $sub->where('store_id', $storeId),
                                )
                                    ->orWhere('id', $record->product_id);
                            });
                        }

                        // Label for initial load (when no search yet)
                        return $query
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "{$p->name} — {$p->barcode}",
                            ])
                            ->toArray();
                    })

                    ->getSearchResultsUsing(function (string $search, callable $get, $record) {

                        $storeId = $get('store_id');
                        if (!$storeId) {
                            return [];
                        }

                        $query = \App\Models\Product::query()
                            ->where('is_active', true)
                            ->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")
                                    ->orWhere('barcode', 'like', "%{$search}%");
                            });

                        // Create mode
                        if (!$record) {
                            $query->whereDoesntHave(
                                'storeInventories',
                                fn($q) => $q->where('store_id', $storeId),
                            );
                        }
                        // Edit mode
                        else {
                            $query->where(function ($q) use ($storeId, $record) {
                                $q->whereDoesntHave(
                                    'storeInventories',
                                    fn($sub) => $sub->where('store_id', $storeId),
                                )
                                    ->orWhere('id', $record->product_id);
                            });
                        }

                        return $query
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "{$p->name} — {$p->barcode}",
                            ])
                            ->toArray();
                    })

                    // Ensures already-selected value renders correctly when form loads
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (!$value) {
                            return null;
                        }

                        $p = \App\Models\Product::find($value);

                        return $p ? "{$p->name} — {$p->barcode}" : null;
                    }),


                Forms\Components\TextInput::make('quantity')->numeric()->required(),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label('Branch')->formatStateUsing(fn($state, $record) => $record->store ? "{$record->store->name} ({$record->store->code})" : '-')->searchable(['store.name', 'store.code'])->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('product.barcode')->label('Part No.')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('product.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Filter by Store'),
            ])
            ->defaultSort('store.name')
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListStoreInventories::route('/'),
            // 'create' => Pages\CreateStoreInventory::route('/create'),
            // 'edit' => Pages\EditStoreInventory::route('/{record}/edit'),
        ];
    }
}
