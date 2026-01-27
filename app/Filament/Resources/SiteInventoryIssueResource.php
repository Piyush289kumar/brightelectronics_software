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
    protected static ?string $navigationGroup = 'Branches';
    protected static ?string $label = 'Stock Out';
    protected static ?string $navigationLabel = 'Stock Out';
    protected static ?int $navigationSort = 10;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Store + Site + Issued By + Notes
                Grid::make(3)->schema([
                    Select::make('store_id')
                        ->label('Branch')
                        ->relationship('store', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn() => Auth::user()?->isStoreManager() ? Auth::user()->store_id : null)
                        ->disabled(fn() => Auth::user()?->isStoreManager())
                        ->dehydrated(), // <-- ensures value is saved even when disabled

                    Select::make('job_card_id')
                        ->label(label: 'Job Card')
                        ->relationship('jobCard', 'job_id')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->reactive(),

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
                                ->label('Spare Parts')
                                ->relationship('product', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->reactive(),

                            // ----------------------------------------------------
                            //  ISSUED QUANTITY (Only editable while issuing)
                            // ----------------------------------------------------
                            TextInput::make('quantity')
                                ->label('Issued Qty')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->maxValue(function (callable $get) {
                                    $storeId = $get('../../store_id');
                                    $productId = $get('product_id');

                                    if (!$storeId || !$productId) {
                                        return null;
                                    }

                                    $inventory = \App\Models\StoreInventory::where('store_id', $storeId)
                                        ->where('product_id', $productId)
                                        ->first();

                                    return $inventory?->quantity ?? 0;
                                })
                                ->helperText(function (callable $get) {
                                    $storeId = $get('../../store_id');
                                    $productId = $get('product_id');
                                    if (!$storeId || !$productId)
                                        return null;

                                    $inventory = \App\Models\StoreInventory::where('store_id', $storeId)
                                        ->where('product_id', $productId)
                                        ->first();

                                    return "Available stock: " . ($inventory?->quantity ?? 0);
                                })
                                ->disabled(fn(callable $get) => $get('../../status') === 'returned'), // ❌ cannot edit when returning


                            // ----------------------------------------------------
                            //  RETURN QUANTITY (Visible only when Returning)
                            // ----------------------------------------------------
                            TextInput::make('return_qty')
                                ->label('Return Qty')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(
                                    fn(callable $get) =>
                                    $get('quantity') ?? 0 // cannot return more than issued
                                )
                                ->helperText(
                                    fn(callable $get) =>
                                    "Issued: " . ($get('quantity') ?? 0)
                                ),

                            Textarea::make('notes')->rows(1),
                        ])
                        ->columns(3)
                        ->required()
                        ->visible(
                            fn() =>
                            auth()->user()->hasAnyRole(['Administrator', 'Store Manager', 'Team Lead'])
                        )

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label('Branch')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('site.name')->label('Site')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('issuer.name')->label('Issued By')->sortable(),

                // Show number of products issued
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Products Count'),

                // Or list product names
                Tables\Columns\TextColumn::make('items.product.name')
                    ->label('Spare Parts')
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
