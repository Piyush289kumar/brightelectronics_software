<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

class Store extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'name',
        'code',
        'location',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'opening_date',
        'account_holder_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'account_type',
        'branch_name',
        'gst_number',
        'pan_number',
        'default_tax_rate',
        'phone',
        'email',
        'status',
        'settings',
        'rent_agreement',
        'gumasta_license',
        'trade_license',
        'ivrs_number',
        'dvr_nvr_ip',
        'dvr_nvr_username',
        'dvr_nvr_password',
        'shutter_lock_number',
        'internet_provider',
        'router_ip',
        'router_username',
        'router_password',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'settings' => 'array',
        'opening_date' => 'date',
        'dvr_nvr_password' => 'encrypted',
        'router_password' => 'encrypted',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function managers()
    {
        return $this->hasMany(User::class)->whereHas('roles', function ($q) {
            $q->where('name', 'manager');
        });
    }

    protected static function booted(): void
    {
        static::creating(function ($store) {

            if (!empty($store->code)) {
                return;
            }

            $date = $store->opening_date
                ? \Carbon\Carbon::parse($store->opening_date)
                : now();

            $prefix = 'BRT-' . $date->format('dmY');

            $count = self::where('code', 'like', $prefix . '%')->count() + 1;

            $store->code = $prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
        });
    }

}
