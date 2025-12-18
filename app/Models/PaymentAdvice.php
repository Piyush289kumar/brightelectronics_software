<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class PaymentAdvice extends Model
{
    use HasFactory, HasRoles;

    protected $table = 'payment_advices';

    protected $fillable = [
        'date',
        'payment_advice_start_date',
        'payment_advice_end_date',
        'vendor_id',
        'purchase_order_id',
        'invoice_id',
        'invoice_amount',
        'payment_doc_no',
        'meta',
    ];

    protected $casts = [
        'date' => 'date',
        'payment_advice_start_date' => 'date',
        'payment_advice_end_date' => 'date',
        'meta' => 'array',
        'invoice_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Vendor Relationship
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Purchase Order Relation (Invoice table)
    public function purchaseOrder()
    {
        return $this->belongsTo(Invoice::class, 'purchase_order_id');
    }

    // Invoice Relation (if stored as invoice number or invoice ID)
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function items()
    {
        return $this->hasMany(PaymentAdviceItem::class);
    }


    /*
    |--------------------------------------------------------------------------
    | Auto-generate Payment Document No
    |--------------------------------------------------------------------------
    */

}
