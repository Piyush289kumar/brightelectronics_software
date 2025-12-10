<?php

namespace App\Filament\Resources\PaymentAdviceResource\Pages;

use App\Filament\Resources\PaymentAdviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentAdvice extends EditRecord
{
    protected static string $resource = PaymentAdviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
