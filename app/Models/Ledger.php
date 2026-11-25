<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\DB;

class Ledger extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = [
        'account_id',
        'store_id',
        'date',
        'transaction_type', // debit / credit
        'payment_mode',
        'amount',
        'balance',
        'journal_entry_id',
        'complain_id',
        'job_card_id',
        'reference',
        'narration',
        'payment_reference_image_path',
        'is_reconciled'
    ];
    protected $casts = [
        'is_reconciled' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }

    public function complain()
    {
        return $this->belongsTo(Complain::class);
    }

    protected static function booted()
    {
        static::creating(function ($ledger) {

            if (auth()->check()) {
                $ledger->created_by = auth()->id();
            }

            $account = Account::find($ledger->account_id);

            if (!$account) {
                return;
            }

            $currentBalance = (float) (
                $account->current_balance ?? $account->opening_balance ?? 0
            );

            $amount = (float) $ledger->amount;

            if ($ledger->transaction_type === 'debit') {
                $newBalance = $currentBalance + $amount;
            } elseif ($ledger->transaction_type === 'credit') {
                $newBalance = $currentBalance - $amount;
            } else {
                $newBalance = $currentBalance;
            }

            $ledger->balance = $newBalance;

            $account->current_balance = $newBalance;
            $account->save();
        });
    }

}
