<?php

namespace App\Filament\Resources\JobCardResource\Pages;

use App\Filament\Resources\JobCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Complain;

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

    // ✅ Runs ONCE on page load — maps DB → form
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // ✅ spare parts mapping
        $data['spare_parts'] = $data['product_id'] ?? [];

        // ✅ AUTO LOAD LEAD SOURCE DATA FROM COMPLAIN
        if (!empty($data['complain_id'])) {
            $complain = Complain::with('leadSource')->find($data['complain_id']);

            $data['lead_incentive_percent'] = $complain?->leadSource?->lead_incentive ?? 0;

            // optional: prefill amount also (will be recalculated anyway)
            $profit = (float) ($data['amount'] ?? 0) - (float) ($data['expense'] ?? 0);

            $data['lead_incentive_amount'] = round(
                ($profit * ($data['lead_incentive_percent'] ?? 0)) / 100,
                2
            );
        }

        return $data;
    }

    // ✅ Runs on save — maps form → DB
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['product_id'] = $data['spare_parts'] ?? [];
        unset($data['spare_parts']);

        return $data;
    }
}