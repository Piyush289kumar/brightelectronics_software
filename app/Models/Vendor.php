<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class Vendor extends Model
{
    use HasFactory, HasRoles;
    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'account_holder_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'account_type',
        'branch_name',
        'gst_number',
        'pan_number',
        'address',
        'city',
        'state',
        'pincode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: A vendor can have many product vendors.
     */
    public function productVendors(): HasMany
    {
        return $this->hasMany(ProductVendor::class);
    }
}
