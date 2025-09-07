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
        'gst_number',
        'pan_number',
        'default_tax_rate',
        'phone',
        'email',
        'status',
        'settings',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'settings' => 'array',
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

}
