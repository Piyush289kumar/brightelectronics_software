<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class JournalPosting extends Model
{
    use HasFactory, HasRoles, SoftDeletes;

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    protected static function booted()
    {
        static::created(function ($posting) {
            $balance = $posting->account->ledgers()->latest('date')->value('balance') ?? 0;
            $balance = $balance + $posting->debit - $posting->credit;

            Ledger::create([
                'account_id' => $posting->account_id,
                'date' => $posting->entry->date,
                'debit' => $posting->debit,
                'credit' => $posting->credit,
                'balance' => $balance,
                'journal_entry_id' => $posting->journal_entry_id,
            ]);
        });
    }
}
