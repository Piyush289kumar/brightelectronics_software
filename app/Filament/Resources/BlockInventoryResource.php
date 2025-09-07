<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockInventoryResource\Pages;
use App\Filament\Resources\BlockInventoryResource\RelationManagers;
use App\Models\BlockInventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class BlockInventoryResource extends Resource
{
    protected static ?string $model = BlockInventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
     protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Block Inventory';    


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('block_id')
                    ->relationship('block', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Forms\Components\TextInput::make('min_quantity')
                    ->numeric()
                    ->default(0)
                    ->label('Minimum Quantity (Alert)')
                    ->required(),

                Forms\Components\Textarea::make('remarks')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('block.name')
                    ->label('Block')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->badge()
                    ->color(
                        fn($state, $record) =>
                        $state <= $record->min_quantity ? 'danger' : 'success'
                    ),

                Tables\Columns\TextColumn::make('min_quantity')
                    ->label('Min Qty'),

                Tables\Columns\TextColumn::make('remarks')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('block_id')
                    ->relationship('block', 'name'),

                Tables\Filters\SelectFilter::make('product_id')
                    ->relationship('product', 'name'),
            ])
            ->actions([
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
            'index' => Pages\ListBlockInventories::route('/'),
            'create' => Pages\CreateBlockInventory::route('/create'),
            'edit' => Pages\EditBlockInventory::route('/{record}/edit'),
        ];
    }
}
