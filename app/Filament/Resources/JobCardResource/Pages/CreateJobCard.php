<?php

namespace App\Filament\Resources\JobCardResource\Pages;

use App\Filament\Resources\JobCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobCard extends CreateRecord
{
    protected static string $resource = JobCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // spare_parts (Repeater) → product_id (DB JSON column)
        $data['product_id'] = $data['spare_parts'] ?? [];
        unset($data['spare_parts']);
        return $data;
    }
}