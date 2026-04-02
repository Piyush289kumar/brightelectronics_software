<?php

namespace App\Filament\Resources\JobCardResource\Pages;

use App\Filament\Resources\JobCardResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Complain;

class CreateJobCard extends CreateRecord
{
    protected static string $resource = JobCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Spare parts mapping
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

        // ✅ AUTO LOAD ENGINEERS FROM COMPLAIN
        if (!empty($data['complain_id'])) {

            $complain = Complain::find($data['complain_id']);
            $assigned = $complain?->assigned_engineers ?? [];

            $data['incentive_percentages'] = collect($assigned)
                ->map(fn($id) => [
                    'user_id' => $id,
                    'percent' => 0,
                    'amount' => 0,
                ])
                ->toArray();
        }

        return $data;
    }

}