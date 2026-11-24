<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PaymentAdvice extends Model
{
    use HasFactory, HasRoles;

    protected $table = 'payment_advices';

    protected $fillable = [
        'payment_advice_number',
        'store_id',
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

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Auto-generate Payment Document No
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function (PaymentAdvice $paymentAdvice) {

            if (filled($paymentAdvice->payment_advice_number)) {
                return;
            }

            $vendor = Vendor::find($paymentAdvice->vendor_id);

            if (!$vendor || blank($vendor->code)) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'Please select a vendor with a valid vendor code.',
                ]);
            }

            $paymentDate = Carbon::parse($paymentAdvice->date ?? now())
                ->format('d_m_Y');

            $prefix = $vendor->code . '_' . $paymentDate;

            // Get the last sequence number for this vendor and date
            $lastNumber = static::where('payment_advice_number', 'like', $prefix . '_%')
                ->orderByRaw('CAST(SUBSTRING_INDEX(payment_advice_number, "_", -1) AS UNSIGNED) DESC')
                ->value('payment_advice_number');

            $nextNumber = $lastNumber
                ? ((int) substr($lastNumber, strrpos($lastNumber, '_') + 1)) + 1
                : 1;

            $paymentAdvice->payment_advice_number = $prefix . '_' . $nextNumber;
        });
    }

}
