<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxSlabResource\Pages;
use App\Filament\Resources\TaxSlabResource\RelationManagers;
use App\Models\TaxSlab;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class TaxSlabResource extends Resource
{
    protected static ?string $model = TaxSlab::class;

    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';
    protected static ?string $navigationGroup = 'Products & Categories';
    protected static ?int $navigationSort = 5;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('rate')->numeric()->required(),
                Forms\Components\Select::make('tax_type')
                    ->options([
                        'gst' => 'GST',
                        'igst' => 'IGST',
                        'cgst_sgst' => 'CGST + SGST',
                    ])
                    ->default('gst')
                    ->required(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('rate')->suffix('%'),
                Tables\Columns\TextColumn::make('tax_type')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->query(fn($query) => $query->where('is_active', true)),
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
            'index' => Pages\ListTaxSlabs::route('/'),
            'create' => Pages\CreateTaxSlab::route('/create'),
            'edit' => Pages\EditTaxSlab::route('/{record}/edit'),
        ];
    }
}
