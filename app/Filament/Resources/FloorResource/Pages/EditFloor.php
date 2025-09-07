<?php

namespace App\Filament\Resources\FloorResource\Pages;

use App\Filament\Resources\FloorResource;
use Filament\Resources\Pages\EditRecord;

class EditFloor extends EditRecord
{
    protected static string $resource = FloorResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if ($user->isStoreManager()) {
            $data['store_id'] = $user->store_id; // enforce manager's store
        }

        return $data;
    }
}
