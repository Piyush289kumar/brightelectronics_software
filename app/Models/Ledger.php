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
        // When a new ledger entry is created.
        static::creating(function ($ledger) {

            if (auth()->check()) {
                $ledger->created_by = auth()->id();
            }

            DB::transaction(function () use ($ledger) {

                $account = Account::query()
                    ->lockForUpdate()
                    ->find($ledger->account_id);

                if (!$account) {
                    return;
                }

                $currentBalance = (float) (
                    $account->current_balance ?? $account->opening_balance ?? 0
                );

                // Update account only after payment is verified.
                if ($ledger->is_reconciled) {

                    $delta = self::getBalanceEffect(
                        $ledger->transaction_type,
                        (float) $ledger->amount
                    );

                    $currentBalance += $delta;

                    $account->current_balance = $currentBalance;
                    $account->save();
                }

                $ledger->balance = $currentBalance;
            });
        });


        // When an existing ledger entry is edited.
        static::updating(function ($ledger) {

            // Only process changes that affect account balance.
            if (
                !$ledger->isDirty([
                    'account_id',
                    'amount',
                    'transaction_type',
                    'is_reconciled',
                ])
            ) {
                return;
            }

            DB::transaction(function () use ($ledger) {

                $oldAccountId = $ledger->getOriginal('account_id');
                $oldAmount = (float) $ledger->getOriginal('amount');
                $oldType = $ledger->getOriginal('transaction_type');
                $oldReconciled = (bool) $ledger->getOriginal('is_reconciled');

                $newAccountId = $ledger->account_id;
                $newAmount = (float) $ledger->amount;
                $newType = $ledger->transaction_type;
                $newReconciled = (bool) $ledger->is_reconciled;

                $deltas = [];

                // Reverse the old transaction if it was already reconciled.
                if ($oldReconciled) {
                    $deltas[$oldAccountId] =
                        ($deltas[$oldAccountId] ?? 0)
                        - self::getBalanceEffect($oldType, $oldAmount);
                }

                // Apply the new transaction only if payment is verified.
                if ($newReconciled) {
                    $deltas[$newAccountId] =
                        ($deltas[$newAccountId] ?? 0)
                        + self::getBalanceEffect($newType, $newAmount);
                }

                $newAccountBalance = null;

                foreach ($deltas as $accountId => $delta) {

                    $account = Account::query()
                        ->lockForUpdate()
                        ->find($accountId);

                    if (!$account) {
                        continue;
                    }

                    $currentBalance = (float) (
                        $account->current_balance
                        ?? $account->opening_balance
                        ?? 0
                    );

                    $account->current_balance = $currentBalance + $delta;
                    $account->save();

                    if ((int) $accountId === (int) $newAccountId) {
                        $newAccountBalance = (float) $account->current_balance;
                    }
                }

                // Keep ledger balance synchronized with its account.
                if ($newAccountBalance === null) {
                    $account = Account::find($newAccountId);

                    $newAccountBalance = $account
                        ? (float) $account->current_balance
                        : 0;
                }

                $ledger->balance = $newAccountBalance;
            });
        });


        // Reverse the account balance when a reconciled ledger is deleted.
        static::deleting(function ($ledger) {

            if (!$ledger->is_reconciled) {
                return;
            }

            DB::transaction(function () use ($ledger) {

                $account = Account::query()
                    ->lockForUpdate()
                    ->find($ledger->account_id);

                if (!$account) {
                    return;
                }

                $currentBalance = (float) (
                    $account->current_balance
                    ?? $account->opening_balance
                    ?? 0
                );

                $delta = self::getBalanceEffect(
                    $ledger->transaction_type,
                    (float) $ledger->amount
                );

                $account->current_balance = $currentBalance - $delta;
                $account->save();
            });
        });
    }


    // Debit increases account balance.
    // Credit decreases account balance.
    protected static function getBalanceEffect(
        string $type,
        float $amount
    ): float {
        return match ($type) {
            'debit' => $amount,
            'credit' => -$amount,
            default => 0,
        };
    }


}
