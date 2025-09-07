<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FloorResource\Pages;
use App\Models\Floor;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class FloorResource extends Resource
{
    protected static ?string $model = Floor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Stores';
    protected static ?string $label = 'Floor';
    protected static ?string $pluralLabel = 'Floors';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn() => Auth::user()?->isStoreManager() ? Auth::user()->store_id : null)
                    ->disabled(fn() => Auth::user()?->isStoreManager()),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('level')
                    ->numeric()
                    ->placeholder('e.g. 0 for Ground, 1 for First'),

                Forms\Components\Select::make('type')
                    ->options([
                        'retail' => 'Retail',
                        'storage' => 'Storage',
                        'office' => 'Office',
                        'mixed' => 'Mixed',
                    ])
                    ->default('retail'),

                Forms\Components\Textarea::make('description'),

                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'under_maintenance' => 'Under Maintenance',
                    ])
                    ->default('active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label('Store')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('level'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'under_maintenance',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFloors::route('/'),
            'create' => Pages\CreateFloor::route('/create'),
            'edit' => Pages\EditFloor::route('/{record}/edit'),
        ];
    }

    /**
     * Restrict floors listing to manager's store.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && $user->isStoreManager()) {
            $query->where('store_id', $user->store_id);
        }

        return $query;
    }
}
