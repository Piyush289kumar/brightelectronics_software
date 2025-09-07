<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductVendorResource\Pages;
use App\Filament\Resources\ProductVendorResource\RelationManagers;
use App\Models\ProductVendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ProductVendorResource extends Resource
{
    protected static ?string $model = ProductVendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Group vendors under a sidebar section
    protected static ?string $navigationGroup = 'Suppliers & Customers';
    // Optional: sort order inside group
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required(),
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->required(),
                Forms\Components\TextInput::make('last_purchase_price')->numeric()->nullable(),
                Forms\Components\TextInput::make('average_purchase_price')->numeric()->nullable(),
                Forms\Components\DatePicker::make('last_purchase_date')->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name'),
                Tables\Columns\TextColumn::make('vendor.name'),
                Tables\Columns\TextColumn::make('last_purchase_price')->money('INR'),
                Tables\Columns\TextColumn::make('average_purchase_price')->money('INR'),
                Tables\Columns\TextColumn::make('last_purchase_date')->date(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('last_purchase_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('to')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('last_purchase_date', '>=', $date))
                            ->when($data['to'], fn($q, $date) => $q->whereDate('last_purchase_date', '<=', $date));
                    }),

                Tables\Filters\Filter::make('has_recent_purchase')
                    ->label('Purchased in last 30 days')
                    ->query(
                        fn($query) =>
                        $query->where('last_purchase_date', '>=', now()->subDays(30))
                    ),

                Tables\Filters\Filter::make('high_purchase_price')
                    ->label('High Purchase Price (> ₹10,000)')
                    ->query(
                        fn($query) =>
                        $query->where('last_purchase_price', '>', 10000)
                    ),
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
            'index' => Pages\ListProductVendors::route('/'),
            'create' => Pages\CreateProductVendor::route('/create'),
            'edit' => Pages\EditProductVendor::route('/{record}/edit'),
        ];
    }
}
