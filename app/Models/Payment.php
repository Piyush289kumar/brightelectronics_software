<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Payment extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = [
        'payable_id',
        'payable_type',
        'invoice_id',
        'type',
        'amount',
        'payment_date',
        'method',
        'reference_no',
        'currency',
        'exchange_rate',
        'status',
        'notes',
        'attachment',
        'received_by',
        'created_by',
        'updated_by',
    ];

    /**
     * 🔹 Polymorphic relation (Invoice, VendorBill, Purchase, etc.)
     */
    public function payable()
    {
        return $this->morphTo();
    }

    /**
     * 🔹 Optional direct link to invoice
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * 🔹 User who received/processed the payment
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * 🔹 User who created the payment entry
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🔹 User who last updated the payment entry
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted()
    {
        static::saved(function ($payment) {
            if ($payment->invoice) {
                $invoice = $payment->invoice;

                $received = $invoice->amount_received;
                if ($received >= $invoice->total_amount) {
                    $invoice->update(['status' => 'paid']);
                } elseif ($received > 0) {
                    $invoice->update(['status' => 'partial']);
                } else {
                    $invoice->update(['status' => 'pending']);
                }
            }
        });
    }

}
