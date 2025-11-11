<?php

namespace App\Filament\Resources\JobCardResource\Pages;

use App\Filament\Resources\JobCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobCards extends ListRecords
{
    protected static string $resource = JobCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
}
