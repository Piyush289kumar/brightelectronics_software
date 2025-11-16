<?php

namespace App\Filament\Resources\ComplainResource\Pages;

use App\Filament\Resources\ComplainResource;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateComplain extends CreateRecord
{
    protected static string $resource = ComplainResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Create customer if not exists
        Customer::firstOrCreate(
            ['phone' => $data['mobile']],
            [
                'name' => $data['name'],
                'email' => $data['customer_email'] ?? null,
                'billing_address' => $data['address'] ?? null,
                'is_active' => true,
            ]
        );

        return $data; // return complain data normally
    }

}
