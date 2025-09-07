<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorResource\Pages;
use App\Filament\Resources\VendorResource\RelationManagers;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Group vendors under a sidebar section
    protected static ?string $navigationGroup = 'Suppliers & Customers';

    // Optional: sort order inside group
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Vendor Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('contact_person')->maxLength(255),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(20),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Tax Details')
                    ->schema([
                        Forms\Components\TextInput::make('gst_number')->maxLength(15),
                        Forms\Components\TextInput::make('pan_number')->maxLength(10),
                    ])->columns(2),

                Forms\Components\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address')->maxLength(255),
                        Forms\Components\TextInput::make('city')->maxLength(100),
                        Forms\Components\TextInput::make('state')->maxLength(100),
                        Forms\Components\TextInput::make('pincode')->maxLength(6),
                    ])->columns(2),

                Forms\Components\Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('contact_person'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('gst_number'),
                Tables\Columns\TextColumn::make('city'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->date(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('city')
                    ->label('City')
                    ->options(
                        fn() => Vendor::query()
                            ->select('city')
                            ->distinct()
                            ->pluck('city', 'city')
                            ->filter()
                    ),

                Tables\Filters\SelectFilter::make('state')
                    ->label('State')
                    ->options(
                        fn() => Vendor::query()
                            ->select('state')
                            ->distinct()
                            ->pluck('state', 'state')
                            ->filter()
                    ),

                Tables\Filters\Filter::make('is_active')
                    ->label('Active Vendors')
                    ->query(fn($query) => $query->where('is_active', true)),

                Tables\Filters\Filter::make('gst_registered')
                    ->label('GST Registered')
                    ->query(fn($query) => $query->whereNotNull('gst_number')),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('to')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['to'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
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
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
