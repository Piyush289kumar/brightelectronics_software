<?php
namespace App\Filament\Resources;
use App\Filament\Resources\SiteInventoryIssueResource\Pages;
use App\Filament\Resources\SiteInventoryIssueResource\RelationManagers;
use App\Models\SiteInventoryIssue;
use Auth;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
class SiteInventoryIssueResource extends Resource
{
    protected static ?string $model = SiteInventoryIssue::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Stores';
    protected static ?string $navigationLabel = 'Site Inventory Issues';
    protected static ?int $navigationSort = 10;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Store + Site + Issued By + Notes
                Grid::make(3)->schema([
                    Select::make('store_id')
                        ->relationship('store', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn() => Auth::user()?->isStoreManager() ? Auth::user()->store_id : null)
                        ->disabled(fn() => Auth::user()?->isStoreManager())
                        ->dehydrated(), // <-- ensures value is saved even when disabled

                    Select::make('site_id')
                        ->label('Site')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->options(function (callable $get) {
                            $storeId = $get('store_id');

                            if (!$storeId) {
                                return [];
                            }

                            return \App\Models\Site::query()
                                ->where('store_id', $storeId)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->default(fn() => Auth::user()?->isStoreManager() ? Auth::user()->site_id : null),

                    Select::make('issued_by')
                        ->label('Issued By')
                        ->relationship('issuer', 'name') // assumes model has issuer() -> belongsTo(User::class, 'issued_by')
                        ->required()
                        ->default(fn() => Auth::id()) // always default to current logged in user
                        ->disabled(fn() => !Auth::user()?->isAdmin())// disable if not admin
                        ->dehydrated(),

                ]),

                Grid::make(1)->schema([
                    // Repeater for multiple products
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->relationship('product', 'name')
                                ->required()
                                ->searchable()
                                ->reactive(), // reactive so we can get its value for max

                            TextInput::make('quantity')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->maxValue(function (callable $get, $set, $record) {
                                    $storeId = $get('../../store_id'); // get the store_id from the parent form
                                    $productId = $get('product_id');

                                    if (!$storeId || !$productId) {
                                        return null; // no limit if store or product not selected yet
                                    }

                                    // fetch current stock from StoreInventory
                                    $inventory = \App\Models\StoreInventory::where('store_id', $storeId)
                                        ->where('product_id', $productId)
                                        ->first();

                                    return $inventory?->quantity ?? 0; // maxValue = available stock
                                })
                                ->helperText(function (callable $get) {
                                    $storeId = $get('../../store_id');
                                    $productId = $get('product_id');

                                    if (!$storeId || !$productId) {
                                        return null;
                                    }

                                    $inventory = \App\Models\StoreInventory::where('store_id', $storeId)
                                        ->where('product_id', $productId)
                                        ->first();

                                    $qty = $inventory?->quantity ?? 0;

                                    return "Available stock: {$qty}";
                                }),

                            Textarea::make('notes')->rows(1),
                        ])
                        ->columns(3)
                        ->required(),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label('Store')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('site.name')->label('Site')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('issuer.name')->label('Issued By')->sortable(),

                // Show number of products issued
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Products Count'),

                // Or list product names
                Tables\Columns\TextColumn::make('items.product.name')
                    ->label('Products')
                    ->listWithLineBreaks()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Issued On')->dateTime(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'issued' => 'Issued',
                    'returned' => 'Returned',
                    'damaged' => 'Damaged',
                ]),
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
            'index' => Pages\ListSiteInventoryIssues::route('/'),
            // 'create' => Pages\CreateSiteInventoryIssue::route('/create'),
            // 'edit' => Pages\EditSiteInventoryIssue::route('/{record}/edit'),
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
