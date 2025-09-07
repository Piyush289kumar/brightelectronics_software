<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class DiscountCoupon extends Model
{
    use HasFactory, HasRoles;
    protected $fillable = [
        'code',
        'type',
        'value',
        'valid_from',
        'valid_until',
        'usage_limit',
        'used_count',
        'status',
    ];

    // Check if coupon is valid now
    public function isValid()
    {
        $now = now();
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }
}
