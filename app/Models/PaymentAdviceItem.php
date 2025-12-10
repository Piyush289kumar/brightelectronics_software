<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class PaymentAdviceItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'payment_advice_id',
        'purchase_order_id',
        'invoice_id',
        'amount',
        'payment_doc_no',
        'po_date',
        'invoice_no',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function paymentAdvice()
    {
        return $this->belongsTo(PaymentAdvice::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(Invoice::class, 'purchase_order_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
