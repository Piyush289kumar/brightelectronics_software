<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Access Control';
    protected static ?int $navigationSort = 8;
    protected static ?string $label = 'User';
    protected static ?string $pluralLabel = 'Users';


    

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->required()
                ->email()
                ->unique(ignoreRecord: true),

            Forms\Components\Select::make('store_id')
                ->label('Store')
                ->relationship('store', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('roles')
                ->label('Roles')
                ->relationship('roles', 'name')
                ->preload(),

            Forms\Components\TextInput::make('password')
                ->password()
                ->maxLength(255)
                ->dehydrateStateUsing(fn($state) => !empty($state) ? bcrypt($state) : null)
                ->required(fn($context) => $context === 'create')
                ->dehydrated(fn($state) => filled($state))
                ->label('Password')
                ->extraAttributes(['autocomplete' => 'new-password']), // prevents autofill
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('store.name')->label('Store')->sortable(),
            Tables\Columns\BadgeColumn::make('roles.name')
                ->label('Roles')
                ->separator(', ')
                ->colors([
                    'primary' => fn($state): bool => $state === 'Super Admin',
                    'warning' => fn($state): bool => $state === 'Store Manager',
                    'success' => fn($state): bool => $state === 'staff',
                ])
                ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state),

            Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Created'),
        ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),                
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            // 'create' => Pages\CreateUser::route('/create'),
            // 'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
