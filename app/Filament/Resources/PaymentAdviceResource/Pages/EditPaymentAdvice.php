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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $rows = $data['items_data'] ?? [];

        if (!empty($rows)) {
            $first = $rows[0];

            $data['purchase_order_id'] = $first['po_id'] ?? null;
            $data['invoice_id'] = $first['invoice_id'] ?? null;
            $data['invoice_amount'] = $first['amount'] ?? 0;
            $data['payment_doc_no'] = $first['payment_doc_no'] ?? null;
        }

        return $data;
    }

}
