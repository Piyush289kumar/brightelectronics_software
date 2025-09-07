<?php

namespace App\Filament\Resources\FloorResource\Pages;

use App\Filament\Resources\FloorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFloor extends CreateRecord
{
    protected static string $resource = FloorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user->isStoreManager()) {
            $data['store_id'] = $user->store_id; // enforce manager's store
        }

        return $data;
    }
}
