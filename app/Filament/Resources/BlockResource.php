<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockResource\Pages;
use App\Models\Block;
use App\Models\Floor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class BlockResource extends Resource
{
    protected static ?string $model = Block::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Stores';
    protected static ?string $label = 'Block';
    protected static ?string $pluralLabel = 'Blocks';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->default(fn() => Auth::user()?->isStoreManager() ? Auth::user()->store_id : null)
                    ->disabled(fn() => Auth::user()?->isStoreManager()),

                Forms\Components\Select::make('floor_id')
                    ->label('Floor')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive() // 👈 important so it updates when store changes
                    ->options(function (callable $get) {
                        $query = Floor::query();

                        // If a store is selected (or fixed for manager), filter by it
                        if ($storeId = $get('store_id')) {
                            $query->where('store_id', $storeId);
                        }

                        return $query->pluck('name', 'id');
                    }),

                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'rack' => 'Rack',
                        'room' => 'Room',
                        'shelf' => 'Shelf',
                        'area' => 'Area',
                    ])
                    ->default('rack'),
                Forms\Components\TextInput::make('zone')->maxLength(100),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'under_maintenance' => 'Under Maintenance',
                    ])
                    ->default('active'),
                Forms\Components\Textarea::make('description')->columnSpanFull(),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label('Store')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('floor.name')->label('Floor')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('zone'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('capacity'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'under_maintenance',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlocks::route('/'),
            'create' => Pages\CreateBlock::route('/create'),
            'edit' => Pages\EditBlock::route('/{record}/edit'),
        ];
    }

    /**
     * Restrict query so managers only see their store's blocks.
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
