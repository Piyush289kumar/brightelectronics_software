<?php

namespace App\Filament\Resources\PaymentAdviceResource\Pages;

use App\Filament\Resources\PaymentAdviceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentAdvice extends CreateRecord
{
    protected static string $resource = PaymentAdviceResource::class;

    protected function afterCreate(): void
    {
        $items = $this->data['items_data'] ?? [];

        foreach ($items as $row) {
            $this->record->items()->create([
                'purchase_order_id' => $row['po_id'],
                'invoice_id' => $row['invoice_id'],
                'amount' => $row['amount'],
                'payment_doc_no' => $row['payment_doc_no'],
                'po_date' => $row['po_date'],
                'invoice_no' => $row['invoice_no'],
            ]);
        }
    }



}
