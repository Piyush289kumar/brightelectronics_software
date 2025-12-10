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
        'vendor_id',
        'purchase_order_id',
        'invoice_id',
        'invoice_amount',
        'payment_doc_no',
        'meta',
    ];

    protected $casts = [
        'date' => 'date',
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

    /*
    |--------------------------------------------------------------------------
    | Auto-generate Payment Document No
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($paymentAdvice) {
            if (empty($paymentAdvice->payment_doc_no)) {
                $nextId = static::max('id') + 1;

                // Example Format: PAD-0001
                $paymentAdvice->payment_doc_no = 'PAD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
