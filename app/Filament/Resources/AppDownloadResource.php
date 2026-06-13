<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppDownloadResource\Pages;
use App\Filament\Resources\AppDownloadResource\RelationManagers;
use App\Models\AppDownload;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AppDownloadResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Mobile App';

    protected static ?string $navigationLabel = 'Download App';

    protected static ?string $label = 'Download App';

    protected static ?string $pluralLabel = 'Download App';

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 10;


    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('id', 1);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('app')
                    ->label('Android App')
                    ->state('Bright Electronics'),

                Tables\Columns\TextColumn::make('download')
                    ->label('Download')
                    ->html()
                    ->state(fn() => '
                    <a href="http://seashell-mandrill-170694.hostingersite.com/brightelectronics_software/brightelectronics.apk"
                       target="_blank"
                       class="px-4 py-2 rounded text-white"
                       style="background:#16a34a;">
                        📱 Download APK
                    </a>
                '),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListAppDownloads::route('/'),
            // 'create' => Pages\CreateAppDownload::route('/create'),
            // 'edit' => Pages\EditAppDownload::route('/{record}/edit'),
        ];
    }
}
