<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inventory::query()
                    ->with('product')
                    ->whereColumn('total_quantity', '<', 'min_stock') // 👈 only low stock items
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Available Stock')
                    ->sortable()
                    ->color(fn($record) => $record->total_quantity < $record->min_stock ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Min Stock')
                    ->sortable(),
            ])
            ->defaultSort('total_quantity', 'asc')
            ->paginated(5);
    }
}
