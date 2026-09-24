<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\JournalEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Accounting';
    protected static ?string $navigationLabel = 'Journal Entries';

     // Permissions Start
      public static function canViewAny(): bool
    {
        return Gate::allows('view_any_journal_entry');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_journal_entry');
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('update_journal_entry');
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('delete_journal_entry');
    }
    // Permission End

    protected static ?int $navigationSort = 8;
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('reference')
                ->required(),

            Forms\Components\DatePicker::make('date')
                ->required()
                ->default(now()),

            Forms\Components\Textarea::make('description')->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('description')->limit(50),
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
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }
}
