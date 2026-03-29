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
                'purchase_order_id' => $row['purchase_order_id'],
                'po_date' => $row['po_date'],                
                'invoice_no' => $row['place_of_supply'],
                'amount' => $row['amount'],
                'payment_doc_no' => $row['payment_doc_no'],
            ]);
        }
    }



}
