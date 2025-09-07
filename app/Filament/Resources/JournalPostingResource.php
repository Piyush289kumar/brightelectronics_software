<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalPostingResource\Pages;
use App\Models\JournalPosting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JournalPostingResource extends Resource
{
    protected static ?string $model = JournalPosting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Journal Postings';
    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('journal_entry_id')
                ->relationship('entry', 'reference')
                ->required(),

            Forms\Components\Select::make('account_id')
                ->relationship('account', 'name')
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('debit')
                ->numeric()
                ->default(0),

            Forms\Components\TextInput::make('credit')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry.reference')->label('Journal Ref')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('account.name')->label('Account')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('debit')->money('inr'),
                Tables\Columns\TextColumn::make('credit')->money('inr'),
            ])
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalPostings::route('/'),
            // 'create' => Pages\CreateJournalPosting::route('/create'),
            // 'edit' => Pages\EditJournalPosting::route('/{record}/edit'),
        ];
    }
}
