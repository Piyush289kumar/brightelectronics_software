<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasRoles;
    protected $fillable = [
        'number',
        'document_type',
        'billable_id',
        'billable_type',
        'destination_store_id',
        'document_date',
        'due_date',
        'place_of_supply',
        'taxable_value',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax',
        'discount',
        'total_amount',
        'status',
        'notes',
        'document_id',
        'created_by',
        'document_path',
    ];

    /**
     * Polymorphic relation for billable (customer, vendor, etc.)
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function destinationStore()
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getAmountReceivedAttribute()
    {
        return $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getBalanceAttribute()
    {
        return $this->total_amount - $this->amount_received;
    }

    /**
     * Boot method to auto set created_by and document number
     */
    protected static function booted()
    {
        static::creating(function ($invoice) {
            // Set created_by automatically
            if (empty($invoice->created_by)) {
                $invoice->created_by = auth()->id();
            }

            // Generate unique number if not already set
            if (empty($invoice->number)) {
                $prefixMap = [
                    'purchase_order' => 'PON',
                    'purchase' => 'PCH',
                    'invoice' => 'INV',
                    'estimate' => 'EST',
                    'quotation' => 'QTN',
                    'credit_note' => 'CNN',
                    'debit_note' => 'DNN',
                    'delivery_note' => 'DLV',
                    'proforma' => 'PRF',
                    'receipt' => 'RCPT',
                    'payment_voucher' => 'PVN',
                    'transfer_order' => 'TRF', // ✅ Stock transfer prefix
                ];

                $prefix = $prefixMap[$invoice->document_type] ?? 'DOC';

                // 🔒 Use transaction + lock to prevent duplicates
                DB::transaction(function () use ($invoice, $prefix) {
                    $lastNumber = static::withTrashed() // Include soft-deleted invoices
                        ->where('document_type', $invoice->document_type)
                        ->lockForUpdate() // prevent race conditions
                        ->orderBy('id', 'desc')
                        ->value('number');

                    $next = 1;
                    if ($lastNumber) {
                        // Extract numeric part (INV-0005 → 5)
                        $lastNumeric = (int) str_replace($prefix . '-', '', $lastNumber);
                        $next = $lastNumeric + 1;
                    }

                    $invoice->number = $prefix . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
                });
            }
        });

        static::deleting(function ($invoice) {
            if ($invoice->document) {
                $invoice->document->delete();
            }
            $invoice->items()->delete();
        });
    }
}
