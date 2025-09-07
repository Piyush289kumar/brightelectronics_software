<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Filament\Resources\BrandResource\RelationManagers;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Str;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Products & Categories';
    protected static ?int $navigationSort = 3;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => $set('slug', Str::slug($state))),
                Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),

                Forms\Components\Section::make('Contact Info')
                    ->schema([
                        Forms\Components\TextInput::make('owner_name')->maxLength(255),
                        Forms\Components\TextInput::make('contact_number')->tel()->maxLength(15),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address_line1')->maxLength(255),
                        Forms\Components\TextInput::make('address_line2')->maxLength(255),
                        Forms\Components\TextInput::make('city')->maxLength(100),
                        Forms\Components\TextInput::make('state')->maxLength(100),
                        Forms\Components\TextInput::make('pincode')->maxLength(6),
                    ])->columns(3),

                Forms\Components\Section::make('Compliance')
                    ->schema([
                        Forms\Components\TextInput::make('gst_number')->maxLength(15)->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('pan_number')->maxLength(10)->unique(ignoreRecord: true),
                    ])->columns(2),

                Forms\Components\Section::make('Brand Media')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->disk('public')
                            ->directory('brands/logos')
                            ->image()
                            ->nullable(),
                        Forms\Components\Textarea::make('description')->rows(4),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')->square(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('owner_name'),
                Tables\Columns\TextColumn::make('gst_number'),
                Tables\Columns\TextColumn::make('state'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])->defaultSort('name')
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
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            // 'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
