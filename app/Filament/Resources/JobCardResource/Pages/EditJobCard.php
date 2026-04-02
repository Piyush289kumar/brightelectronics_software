<?php

namespace App\Filament\Resources\JobCardResource\Pages;

use App\Filament\Resources\JobCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJobCard extends EditRecord
{
    protected static string $resource = JobCardResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Runs ONCE on page load — maps DB product_id JSON → spare_parts Repeater
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['spare_parts'] = $data['product_id'] ?? [];
        return $data;
    }

    // Runs on save — maps spare_parts Repeater → DB product_id JSON column
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['product_id'] = $data['spare_parts'] ?? [];
        unset($data['spare_parts']);
        return $data;
    }
}