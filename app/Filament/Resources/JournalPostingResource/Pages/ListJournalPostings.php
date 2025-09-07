<?php

namespace App\Filament\Resources\JournalPostingResource\Pages;

use App\Filament\Resources\JournalPostingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJournalPostings extends ListRecords
{
    protected static string $resource = JournalPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
