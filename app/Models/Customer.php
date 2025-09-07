<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Customer extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',

        'billing_address',
        'billing_city',
        'billing_state',
        'billing_postal_code',

        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',

        'gstin',
        'pan',
        'place_of_supply',

        'contact_person_name',
        'contact_person_phone',
        'contact_person_email',

        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_name',

        'business_type',
        'credit_limit',
        'notes',
        'is_active',
    ];

    // You can add relationships like invoices:
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
