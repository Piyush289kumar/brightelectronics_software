<?php

namespace App\Filament\Resources\ComplainResource\Pages;

use App\Filament\Resources\ComplainResource;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComplain extends EditRecord
{
    protected static string $resource = ComplainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Customer::firstOrCreate(
            ['phone' => $data['mobile']],
            [
                'name' => $data['name'],
                'email' => $data['customer_email'] ?? null,
                'billing_address' => $data['address'] ?? null,
                'is_active' => true,
            ]
        );

        return $data;
    }

}
