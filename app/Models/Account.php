<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Account extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = [
        'account_name',
        'account_type',
        'bank_name',
        'account_number',
        'ifsc_code',
        'upi_id',
        'opening_balance',
        'balance_type',
        'current_balance',
        'is_active',
    ];

    // Example: link to ledgers
    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }
}
