<?php

namespace App\Filament\Resources\JournalPostingResource\Pages;

use App\Filament\Resources\JournalPostingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJournalPosting extends EditRecord
{
    protected static string $resource = JournalPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
