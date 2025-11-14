<?php

namespace App\Filament\Resources\JobCardResource\Pages;

use App\Filament\Resources\JobCardResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateJobCard extends CreateRecord
{
    protected static string $resource = JobCardResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}