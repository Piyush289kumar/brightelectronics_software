<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class StoreTarget extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'store_id',
        'year',
        'month',
        'amount',
        'collected_amount',
        'include_previous',
        'previous_remaining_sum',
        'distributed',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_amount' => 'decimal:2', // Add this line
        'previous_remaining_sum' => 'decimal:2',
        'include_previous' => 'boolean',
        'distributed' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function userTargets()
    {
        return $this->hasMany(UserTarget::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
