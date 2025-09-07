<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountCouponResource\Pages;
use App\Filament\Resources\DiscountCouponResource\RelationManagers;
use App\Models\DiscountCoupon;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class DiscountCouponResource extends Resource
{
    protected static ?string $model = DiscountCoupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';
    protected static ?string $navigationGroup = 'Products & Categories';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')->required()->unique(ignoreRecord: true),
                Select::make('type')->options([
                    'percentage' => 'Percentage',
                    'fixed' => 'Fixed Amount',
                ])->required(),
                TextInput::make('value')->numeric()->required(),
                DatePicker::make('valid_from')->nullable(),
                DatePicker::make('valid_until')->nullable(),
                TextInput::make('usage_limit')->numeric()->nullable(),
                Select::make('status')->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ])->default('active')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('type')->sortable(),
                TextColumn::make('value')->sortable(),
                TextColumn::make('usage_limit')->sortable(),
                TextColumn::make('used_count')->sortable(),
                TextColumn::make('valid_from')->date()->sortable(),
                TextColumn::make('valid_until')->date()->sortable(),
                TextColumn::make('status')->sortable(),
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
            'index' => Pages\ListDiscountCoupons::route('/'),
            // 'create' => Pages\CreateDiscountCoupon::route('/create'),
            // 'edit' => Pages\EditDiscountCoupon::route('/{record}/edit'),
        ];
    }
}
