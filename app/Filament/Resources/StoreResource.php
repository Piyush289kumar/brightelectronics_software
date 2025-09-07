<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Filament\Resources\StoreResource\RelationManagers;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'Stores';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('code')->required()->maxLength(50),
                        Forms\Components\TextInput::make('location')->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Address Details')
                    ->schema([
                        Forms\Components\TextInput::make('address')->maxLength(255),
                        Forms\Components\TextInput::make('city')->maxLength(100),
                        Forms\Components\TextInput::make('state')->maxLength(100),
                        Forms\Components\TextInput::make('pincode')->maxLength(6),
                        Forms\Components\TextInput::make('country')->default('India')->maxLength(100),
                    ])->columns(3),

                Forms\Components\Section::make('GST & Tax')
                    ->schema([
                        Forms\Components\TextInput::make('gst_number')->maxLength(15),
                        Forms\Components\TextInput::make('pan_number')->maxLength(10),
                        Forms\Components\TextInput::make('default_tax_rate')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.00),
                    ])->columns(3),

                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('phone')->maxLength(15),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    ])->columns(2),

                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),

                Forms\Components\Textarea::make('settings')
                    ->json() // Filament supports JSON editing from v3+
                    ->nullable()
                    ->helperText('JSON for store-specific settings'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->sortable(),
                Tables\Columns\TextColumn::make('city')->sortable(),
                Tables\Columns\TextColumn::make('state')->sortable(),
                Tables\Columns\TextColumn::make('gst_number'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('status')
                    ->query(fn($query) => $query->where('status', 'active'))
                    ->label('Active Stores'),
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
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
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
            // Show only the store assigned to the manager
            $query->where('id', $user->store_id);
        }

        return $query;
    }
}
