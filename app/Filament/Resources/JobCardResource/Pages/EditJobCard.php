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
        // ✅ Spare parts mapping
        $data['spare_parts'] = $data['product_id'] ?? [];

        if (!empty($data['complain_id'])) {

            $complain = Complain::with('leadSource')->find($data['complain_id']);

            // ✅ Lead %
            $leadPercent = (float) ($complain?->leadSource?->lead_incentive ?? 0);
            $data['lead_incentive_percent'] = $leadPercent;

            // ✅ Lead Amount (approx preload)
            $profit = (float) ($data['amount'] ?? 0) - (float) ($data['expense'] ?? 0);
            $data['lead_incentive_amount'] = round(($profit * $leadPercent) / 100, 2);

            // ✅ AUTO LOAD ENGINEERS (ONLY IF EMPTY)
            if (empty($data['incentive_percentages'])) {

                $assigned = $complain?->assigned_engineers ?? [];

                $data['incentive_percentages'] = collect($assigned)
                    ->map(fn($id) => [
                        'user_id' => $id,
                        'percent' => 0,
                        'amount' => 0,
                    ])
                    ->toArray();
            }
        }

        return $data;
    }

    // ✅ Runs on save — maps form → DB
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // spare parts → product_id
        $data['product_id'] = collect($data['spare_parts'] ?? [])
            ->map(function ($item) {
                return [
                    'product_id' => is_array($item['product_id'])
                        ? $item['product_id']['value'] ?? null
                        : $item['product_id'],
                    'qty' => (int) ($item['qty'] ?? 1),
                ];
            })
            ->filter(fn($item) => !empty($item['product_id']))
            ->values()
            ->toArray();

        unset($data['spare_parts']);

        // ensure engineer data saved
        $data['incentive_percentages'] = array_values($data['incentive_percentages'] ?? []);

        return $data;
    }
}