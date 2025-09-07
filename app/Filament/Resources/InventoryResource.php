<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\Inventory;
use App\Models\StoreInventory;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $pluralModelLabel = 'Central Inventory ';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = -1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required(),

                Grid::make(1) // default 1 column grid (vertical stack)
                    ->schema([
                        TextInput::make('total_quantity')
                            ->label('Total Quantity')
                            ->numeric()
                            ->disabled(),

                        Placeholder::make('store_inventories_data')
                            ->label('Store-wise Quantity')
                            ->content(function ($get) {
                                $productId = $get('product_id');
                                if (!$productId) {
                                    return new HtmlString('<p style="font-style: italic; color: #6b7280;">Select a product to view store inventory.</p>');
                                }

                                $storeInventories = StoreInventory::where('product_id', $productId)->with('store')->get();

                                if ($storeInventories->isEmpty()) {
                                    return new HtmlString('<p style="font-style: italic; color: #6b7280;">No store inventory found for this product.</p>');
                                }

                                $html = '<table style="width:100%; border-collapse: collapse; font-family: Arial, sans-serif;">';
                                $html .= '<thead>';
                                $html .= '<tr style="background-color: none;">';
                                $html .= '<th style="text-align:left; padding: 8px; border-bottom: 2px solid #d1d5db; color: #fff;">Store</th>';
                                $html .= '<th style="text-align:right; padding: 8px; border-bottom: 2px solid #d1d5db; color: #fff;">Quantity</th>';
                                $html .= '</tr>';
                                $html .= '</thead><tbody>';

                                foreach ($storeInventories as $inv) {
                                    $html .= '<tr>';
                                    $html .= '<td style="text-align:left; padding: 8px; border-bottom: 1px solid #e5e7eb;">' . e($inv->store->name) . '</td>';
                                    $html .= '<td style="text-align:right; padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600;">' . number_format($inv->quantity) . '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table>';

                                return new HtmlString($html);
                            }),
                    ])
                    ->columnSpan('half'),


                Grid::make(2)  // 2 columns grid
                    ->schema([
                        Forms\Components\TextInput::make('min_stock')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('max_stock')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\Textarea::make('meta')
                            ->json()
                            ->nullable(),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columnSpan('full'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('total_quantity')->sortable(),
                Tables\Columns\TextColumn::make('min_stock'),
                Tables\Columns\TextColumn::make('max_stock'),
                Tables\Columns\BooleanColumn::make('is_active'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
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
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
