<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Ledger extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = [
        'account_id',
        'date',
        'transaction_type', // debit / credit
        'amount',
        'balance',
        'journal_entry_id',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    protected static function booted()
    {
        static::creating(function ($ledger) {
            if (auth()->check()) {
                $ledger->created_by = auth()->id();
            }

            // 🔹 Get related account
            $account = Account::find($ledger->account_id);

            if ($account) {
                // Calculate new balance
                $currentBalance = $account->current_balance ?? $account->opening_balance;

                if ($ledger->transaction_type === 'debit') {
                    $ledger->balance = $currentBalance + $ledger->amount;
                } elseif ($ledger->transaction_type === 'credit') {
                    $ledger->balance = $currentBalance - $ledger->amount;
                }

                // Save back to account
                $account->current_balance = $ledger->balance;
                $account->save();
            }
        });
    }

}
