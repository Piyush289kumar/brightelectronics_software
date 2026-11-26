<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppDownloadResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class AppDownloadResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Backup Management';

    protected static ?string $navigationLabel = 'Backup Download';

    protected static ?string $label = 'Backup Download';

    protected static ?string $pluralLabel = 'Backup Downloads';

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 10;

    protected static function permission(string $action): string
    {
        return "{$action}_app::download";
    }

    protected static function databaseBackupDirectory(): string
    {
        return storage_path('app/private/backups/database_backups');
    }

    protected static function projectBackupDirectory(): string
    {
        return storage_path('app/private/backups/project_backups');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('id', 1);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    protected static function latestBackup(string $directory): ?\SplFileInfo
    {
        if (!File::isDirectory($directory)) {
            return null;
        }

        return collect(File::files($directory))
            ->filter(fn($file) => strtolower($file->getExtension()) === 'zip')
            ->sortByDesc(fn($file) => $file->getMTime())
            ->first();
    }

    protected static function backupDetails(string $directory): string
    {
        $file = static::latestBackup($directory);

        if (!$file) {
            return 'No backup found';
        }

        return $file->getFilename()
            . ' | '
            . date('d M Y, h:i A', $file->getMTime());
    }

    protected static function downloadBackup(string $directory)
    {
        $file = static::latestBackup($directory);

        abort_unless($file, 404, 'Backup file not found.');

        return response()->download(
            $file->getPathname(),
            $file->getFilename()
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('database_backup')
                    ->label('Database Backup')
                    ->state(fn() => static::backupDetails(
                        static::databaseBackupDirectory()
                    ))
                    ->wrap(),

                // Tables\Columns\TextColumn::make('project_backup')
                //     ->label('Project Backup')
                //     ->state(fn () => static::backupDetails(
                //         static::projectBackupDirectory()
                //     ))
                //     ->wrap(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('download_database')
                    ->label('Download Database')
                    ->icon('heroicon-o-circle-stack')
                    ->color('success')
                    ->button()
                    ->visible(fn() => static::latestBackup(
                        static::databaseBackupDirectory()
                    ) !== null)
                    ->action(fn() => static::downloadBackup(
                        static::databaseBackupDirectory()
                    )),

                // Tables\Actions\Action::make('download_project')
                //     ->label('Download Project')
                //     ->icon('heroicon-o-archive-box')
                //     ->color('info')
                //     ->button()
                //     ->visible(fn () => static::latestBackup(
                //         static::projectBackupDirectory()
                //     ) !== null)
                //     ->action(fn () => static::downloadBackup(
                //         static::projectBackupDirectory()
                //     )),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppDownloads::route('/'),
        ];
    }
}
