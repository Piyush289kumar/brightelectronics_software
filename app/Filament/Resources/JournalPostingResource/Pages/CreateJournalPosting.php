<?php

namespace App\Filament\Resources\JournalPostingResource\Pages;

use App\Filament\Resources\JournalPostingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalPosting extends CreateRecord
{
    protected static string $resource = JournalPostingResource::class;
}
