<?php

namespace App\Filament\Resources\AppDownloadResource\Pages;

use App\Filament\Resources\AppDownloadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppDownload extends EditRecord
{
    protected static string $resource = AppDownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
