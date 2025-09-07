<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Filament\Resources\InventoryMovementResource\RelationManagers;
use App\Models\InventoryMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use function Livewire\wrap;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Inventory Management';

    protected static ?int $navigationSort = 4;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')->relationship('store', 'name')->nullable(),
                Forms\Components\Select::make('product_id')->relationship('product', 'name')->required(),
                Forms\Components\Select::make('user_id')->relationship('user', 'name')->nullable(),
                Forms\Components\Select::make('type')->options([
                    'purchase' => 'Purchase',
                    'sale' => 'Sale',
                    'transfer_in' => 'Transfer In',
                    'transfer_out' => 'Transfer Out',
                    'adjustment_in' => 'Adjustment In',
                    'adjustment_out' => 'Adjustment Out',
                ])->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->required(),
                Forms\Components\TextInput::make('price')->numeric()->required(),
                Forms\Components\TextInput::make('reference_type')->nullable(),
                Forms\Components\TextInput::make('reference_id')->nullable(),
                Forms\Components\Textarea::make('remarks')->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('remarks')->sortable()->wrap(false),
                Tables\Columns\TextColumn::make('store.name')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('User'),
                Tables\Columns\TextColumn::make('type')->sortable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('price'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'purchase' => 'Purchase',
                    'sale' => 'Sale',
                    'transfer_in' => 'Transfer In',
                    'transfer_out' => 'Transfer Out',
                    'adjustment_in' => 'Adjustment In',
                    'adjustment_out' => 'Adjustment Out',
                    'store_demand' => 'Store Demand',
                    'store_demand_approved' => 'Store Demand Approved',
                ]),
                Tables\Filters\SelectFilter::make('store_id')->relationship('store', 'name'),
                Tables\Filters\SelectFilter::make('product_id')->relationship('product', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListInventoryMovements::route('/'),
            'create' => Pages\CreateInventoryMovement::route('/create'),
            'edit' => Pages\EditInventoryMovement::route('/{record}/edit'),
        ];
    }
}
